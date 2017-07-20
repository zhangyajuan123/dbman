<?php
return array (
    'fields' =>
        array (
            'id' =>
                array (
                    'name' => 'id',
                    'type' => 'int(10)',
                    'notnull' => false,
                    'default' => NULL,
                    'primary' => true,
                    'autoinc' => true,
                ),
            'start_status' =>
                array (
                    'name' => 'start_status',
                    'type' => 'enum(\'Y\',\'N\')',
                    'notnull' => true,
                    'default' => 'Y',
                    'primary' => false,
                    'autoinc' => false,
                    'comment' => '启用状态',
                ),
            'login_status' =>
                array (
                    'name' => 'login_status',
                    'type' => 'enum(\'Y\',\'N\')',
                    'notnull' => true,
                    'default' => 'N',
                    'primary' => false,
                    'autoinc' => false,
                    'comment' => '登陆状态',
                ),
            'password' =>
                array (
                    'name' => 'password',
                    'type' => 'char(32)',
                    'notnull' => false,
                    'default' => NULL,
                    'primary' => false,
                    'autoinc' => false,
                    'comment' => '密码',
                ),
            'age' =>
                array (
                    'name' => 'age',
                    'type' => 'int(2)',
                    'notnull' => true,
                    'default' => NULL,
                    'primary' => false,
                    'autoinc' => false,
                ),
            'sex' =>
                array (
                    'name' => 'sex',
                    'type' => 'enum(\'M\',\'W\')',
                    'notnull' => true,
                    'default' => 'M',
                    'primary' => false,
                    'autoinc' => false,
                ),
            'info' =>
                array (
                    'name' => 'info',
                    'type' => 'longtext',
                    'notnull' => false,
                    'default' => NULL,
                    'primary' => false,
                    'autoinc' => false,
                ),
            'savetime' =>
                array (
                    'name' => 'savetime',
                    'type' => 'int(10)',
                    'notnull' => false,
                    'default' => NULL,
                    'primary' => false,
                    'autoinc' => false,
                ),
            'uname' =>
                array (
                    'name' => 'uname',
                    'type' => 'char(30)',
                    'notnull' => true,
                    'default' => NULL,
                    'primary' => false,
                    'autoinc' => false,
                ),
        ),
    'index' =>
        array (
            'idx_demo_uname' =>
                array (
                    'name' => 'idx_demo_uname',
                    'type' => 'unique',
                    'method' => '',
                    'fields' => 'uname',
                ),
            'idx_demo_savetime' =>
                array (
                    'name' => 'idx_demo_savetime',
                    'type' => 'normal',
                    'method' => '',
                    'fields' => 'savetime',
                ),
            'idx_demo_status' =>
                array (
                    'name' => 'idx_demo_status',
                    'type' => 'normal',
                    'method' => '',
                    'fields' =>
                        array (
                            0 => 'start_status',
                            1 => 'login_status',
                        ),
                ),
        ),
    'version' => '1.0',
    'engine' => 'innodb',
    'comment' => 'demo',
);