<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * mod/reader/db/access.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

$capabilities = array(

    'mod/reader:addinstance' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => array(
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/course:manageactivities'
    ),

    'mod/reader:manageattempts' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'riskbitmask'  => RISK_DATALOSS,
        'archetypes'   => array(
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW
        )
    ),

    'mod/reader:managebooks' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'riskbitmask'  => RISK_DATALOSS,
        'archetypes'   => array(
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW
        )
    ),

    'mod/reader:manageutilities' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'riskbitmask'  => RISK_DATALOSS,
        'archetypes'   => array(
            'manager'        => CAP_ALLOW
        )
    ),

    'mod/reader:managequizzes' => array(
        'captype'      => 'write',
        'riskbitmask'  => RISK_DATALOSS,
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => array(
            'manager'        => CAP_ALLOW
        )
    ),

    'mod/reader:manageremotesites' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => array(
            'manager'        => CAP_ALLOW
        )
    ),

    'mod/reader:manageusers' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'riskbitmask'  => RISK_PERSONAL,
        'archetypes'   => array(
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW
        )
    ),

    'mod/reader:viewbooks' => array(
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => array(
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW
        )
    ),


    'mod/reader:viewreports' => array(
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'riskbitmask'  => RISK_PERSONAL,
        'archetypes'   => array(
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW
        )
    ),
);
