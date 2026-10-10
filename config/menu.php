<?php

return [
    ['label' => 'Dashboard',    'route' => 'dashboard', 'roles' => '*'],
    ['label' => 'Patients',     'route' => 'patients.index',     'active' => 'patients.*',     'roles' => ['admin', 'receptionist', 'nurse', 'doctor']],
    ['label' => 'Staff',             'route' => 'staff.index',   'active' => 'staff.*',    'roles' => ['admin']],
    ['label' => 'Departments', 'route' => 'departments.index', 'active' => 'departments.*', 'roles' => ['admin']],
    ['label' => 'Appointments', 'route' => 'appointments.index', 'active' => 'appointments.*', 'roles' => ['admin', 'receptionist', 'doctor']],
    ['label' => 'Triage',       'route' => 'triage.index',        'active' => 'triage.*',        'roles' => ['admin', 'nurse']],
    ['label' => 'Consultation', 'route' => 'consultations.index', 'active' => 'consultations.*', 'roles' => ['doctor']],
    // ['label' => 'Laboratory', 'route' => 'lab.index',      'active' => 'lab.*',      'roles' => ['admin', 'lab', 'doctor']],
    ['label' => 'Laboratory', 'route' => 'lab.index', 'active' => ['lab.index', 'lab.show', 'lab.report', 'lab.tests.*', 'lab.attachments.*'], 'roles' => ['admin', 'lab', 'doctor']],
    ['label' => 'External Lab Request', 'route' => 'lab.referrals.create', 'active' => 'lab.referrals.*', 'roles' => ['admin', 'receptionist', 'lab']],
    ['label' => 'Pharmacy',   'route' => 'pharmacy.index', 'active' => 'pharmacy.*', 'roles' => ['admin', 'pharmacist']],
    ['label' => 'Billing', 'route' => 'billing.index', 'active' => 'billing.*', 'roles' => ['admin', 'cashier']],
    ['label' => 'Hospital Settings', 'route' => 'settings.edit', 'active' => 'settings.*', 'roles' => ['admin']],

];
