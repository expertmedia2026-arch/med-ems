<?php

return [
    ['label' => 'Dashboard',           'route' => 'portal.home',               'active' => 'portal.home'],
    ['label' => 'My Visits',           'route' => 'portal.visits.index',       'active' => 'portal.visits.*'],
    ['label' => 'Appointments',        'route' => 'portal.appointments.index', 'active' => 'portal.appointments.*'],
    // ['label' => 'Invoices & Payments', 'route' => '#'],
    ['label' => 'Invoices & Payments', 'route' => 'portal.invoices.index', 'active' => 'portal.invoices.*'],
];
