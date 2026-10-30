<?php

/*
| Patient portal navigation has no staff role checks because portal middleware
| already restricts the surface to the linked patient. Module flags still hide
| features that the current hospital has not enabled.
*/
return [
    ['label' => 'Dashboard',           'route' => 'portal.home',               'active' => 'portal.home'],
    ['label' => 'My Visits',           'route' => 'portal.visits.index',       'active' => 'portal.visits.*'],
    ['label' => 'Appointments',        'route' => 'portal.appointments.index', 'active' => 'portal.appointments.*', 'module' => 'appointments'],
    // ['label' => 'Invoices & Payments', 'route' => '#'],
    ['label' => 'Invoices & Payments', 'route' => 'portal.invoices.index', 'active' => 'portal.invoices.*'],
];
