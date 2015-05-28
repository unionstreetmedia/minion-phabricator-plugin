<?php

namespace Minion\Plugins;

require 'vendor/autoload.php';

$Phabricator = new \Minion\Plugin(
    'Phabricator',
    'Minion, talk to Phabricator.',
    'Ryan N. Freebern / ryan@freebern.org'
);

return $Phabricator

->on('PRIVMSG', function ($data) use ($Phabricator) {
    $pattern =
        '@'.
        '(?<!/)(?:^|\b)'. // Negative lookbehind prevent matching "/D123".
        '([A-Z])(\d+)'.
        '(?:\b|$)'.
        '@';
    $matches = $Phabricator->matchCommand($data, $pattern);
    if ($matches) {
        $object_names = array();
        $output = array();
        foreach ($matches as $match) {
            $object_names[] = $match[1].$match[2];
        }

        if (count($object_names)) {
            $response = $Phabricator->conduit->Phid('lookup', array('names' => $object_names));

            foreach ($object_names as $object) {
                if ($object == 'T1000') {
                    $Phabricator->Minion->msg("T1000: A mimetic poly-alloy assassin controlled by Skynet");
                } else {
                    if (is_object($response) and property_exists($response, $object)) {
                        $object = $response->$object;
                        $Phabricator->Minion->msg("{$object->typeName} {$object->fullName} ({$object->status}) {$object->uri}", $data['arguments'][0]);
                    }
                }
            }
        }
    }
})

->on('before-loop', function () use ($Phabricator) {
    $url = $Phabricator->conf('URL');
    $user = $Phabricator->conf('Username');
    $token = $Phabricator->conf('Token');
    $client = new \Phabricator\Client\CurlClient();
    $Phabricator->conduit = new \Phabricator\Phabricator($client, $url, $user, $token);
    
    $response = $Phabricator->conduit->Feed('query', array('limit' => 1));
    $latest = 0;
    foreach ($response as $story) {
        $latest = $story->chronologicalKey;
    }

    $Phabricator->last = array();
    foreach ($Phabricator->conf('Feed') as $channel => $config) {
        $Phabricator->last[$channel] = array(time(), $latest);
    }
})

->on('loop-end', function () use ($Phabricator) {
    foreach ($Phabricator->conf('Feed') as $channel => $config) {
        $Phabricator->Minion->log("Feeding Phabricator info to $channel", 'INFO');
        $now = time();
        $interval = isset($config['interval']) ? $config['interval'] : 30;
        if ($now > $Phabricator->last[$channel][0] + $interval) {
            $Phabricator->last[$channel][0] = $now;
            $Phabricator->Minion->log("Interval $interval has elapsed.", 'INFO');
            $cursor = 0;
            $page = 0;
            $stories = array();
            $last = $Phabricator->last[$channel][1];
            $Phabricator->Minion->log("Latest key is $last", 'INFO');
            while ($page++ < 5) {
                $Phabricator->Minion->log("Page $page", 'INFO');
                $query = array('limit' => 10, 'view' => 'text');
                if ($cursor) {
                    $query['after'] = $cursor;
                }
                $response = $Phabricator->conduit->Feed('query', $query);
                $Phabricator->Minion->log(json_encode($response, JSON_PRETTY_PRINT), 'INFO');
                if (is_object($response)) {
                    foreach ($response as $phid => $story) {
                        if ($story->chronologicalKey == $last) {
                            $Phabricator->Minion->log("Found latest key {$story->chronologicalKey}, so done", 'INFO');
                            break 2;
                        }
                        if ($story->chronologicalKey > $Phabricator->last[$channel][1]) {
                            $Phabricator->Minion->log("Including story $phid", 'INFO');
                            $stories[$story->objectPHID] = $story->text;
                            $Phabricator->last[$channel][1] = $story->chronologicalKey;
                        }
                        if (!$cursor or $story->chronologicalKey < $cursor) {
                            $Phabricator->Minion->log("New key is {$story->chronologicalKey}", 'INFO');
                            $cursor = $story->chronologicalKey;
                        }
                    }
                }
            }
            $Phabricator->Minion->log("Collected " . count($stories) . " stories.", 'INFO');
            $objects = $Phabricator->conduit->Phid('query', array('phids' => array_keys($stories)));
            foreach ($stories as $objectPHID => $text) {
                $url = property_exists($objects, $objectPHID) ? $objects->$objectPHID->uri : null;
                $Phabricator->Minion->msg("$text $url", $channel);
            }
        }
    }
});
