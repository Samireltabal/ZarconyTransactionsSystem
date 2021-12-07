<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'prefix'        => 'reports',
    'middleware'    => ['api', 'role:admin']    
];