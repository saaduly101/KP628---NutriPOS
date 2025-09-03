<?php
return [
  'mail' => [
  'enabled' => true,
  'from_email' => 'no-reply@nutripos.local',
  'from_name'  => 'NutriPOS',
  'smtp_host'  => 'sandbox.smtp.mailtrap.io',
  'smtp_port'  => 2525,
  'smtp_user'  => '9be321bbbcac12',
  'smtp_pass'  => '032def420ffe71',
  'smtp_secure'=> 'tls',
],
  'db' => [
    'driver'  => 'mysql',
    'host'    => '127.0.0.1',
    'port'    => '3306',
    'name'    => 'nutripos',
    'user'    => 'root',
    'pass'    => '',
    'charset' => 'utf8mb4',
  ],
];
