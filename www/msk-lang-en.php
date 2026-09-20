<?php

// English dictionary for the AGA shell page.
if (!isset($GLOBALS['AGN_CFG_LANGUAGE'])) {
$GLOBALS['AGN_CFG_LANGUAGE'] = [
    'code'   => 'en',
    'locale' => 'en-GB',

    'app_dir' => '../app',

    'agt_dir' => '../agt',

    'wmx_dir' => '../wmx',

    'strings' => [
        'shell.toggle_nav'      => 'Toggle navigation',

        'shell.menu'            => 'Menu',
        'shell.app_home'        => 'Application home',
        'shell.app_title'       => 'AGRINEXO AGA',

        'shell.fields'          => 'Crops',
        'shell.operations'      => 'Operations',
        'shell.animals'         => 'Animals',
        'shell.report'          => 'Reports',
        'shell.kbase'           => 'Knowledge Base',
        'shell.help'            => 'Help',
        'shell.install'         => 'Install app',

        'shell.assistant'       => 'AI Assistant',

        'shell.account'         => 'Account',
        'shell.profile'         => 'Profile',
        'shell.change_password' => 'Change Password',
        'shell.sign_out'        => 'Sign Out',
        'shell.sign_in'         => 'Sign In',

        'shell.app_name'        => 'Agrinexo AGA',

        'shell.offline_title'   => 'You are offline',
        'shell.offline_message' => 'Agrinexo AGA needs a network connection to work. Reconnect and try again.',
        'shell.offline_retry'   => 'Try again',

        'shell.dialog_ok'       => 'OK',
        'shell.dialog_cancel'   => 'Cancel',

        'shell.superuser_only'  => 'Access restricted to the system administrator.',
    ],
];
}
