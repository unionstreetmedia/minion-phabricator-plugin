# Minion, talk to Phabricator

Make your minion talk to Phabricator. This essentially duplicates (a good portion of) the functionality that Phabricator's PhabricatorBot has.

## Configuration
Put something like the following in your minion's `config.php`.

    $this->PluginConfig['Phabricator'] = array(
        'URL' => 'http://phabricator.yourcompany.com',
        'Username' => 'minion',
        'Token' => '...',
        'Feed' => array(
            '#mychannel' => array(
                'interval' => 20 // seconds
            )
        )
    );

## What it does
* Responds to mentions of Phabricator objects (like T123, D456, P789, F1011) with their name and URL.
* Sends the feed info into the specified channel.
