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
            $url = $Phabricator->conf('URL');
            $user = $Phabricator->conf('Username');
            $token = $Phabricator->conf('Token');
            $client = new \Phabricator\Client\CurlClient();
            $conduit = new \Phabricator\Phabricator($client, $url, $user, $token);
            $response = $conduit->Phid('lookup', array('names' => $object_names));

            foreach ($object_names as $object) {
                if ($object == 'T1000') {
                    $Phabricator->Minion->msg("T1000: A mimetic poly-alloy assassin controlled by Skynet");
                } else {
                    $object = $response->$object;
                    $Phabricator->Minion->msg("{$object->typeName} {$object->fullName} ({$object->status}) {$object->uri}", $data['arguments'][0]);
                }
            }
        }
    }
});
