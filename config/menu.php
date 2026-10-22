<?php

return [
    ['label' => 'Dashboard',    'route' => 'dashboard', 'roles' => '*'],
    ['label' => 'Patients',     'route' => 'patients.index',     'active' => 'patients.*',     'roles' => ['admin', 'receptionist', 'nurse', 'doctor']],
    ['label' => 'Staff',             'route' => 'staff.index',   'active' => 'staff.*',    'roles' => ['admin', 'chief_accountant']],
    ['label' => 'Departments', 'route' => 'departments.index', 'active' => 'departments.*', 'roles' => ['admin']],
    ['label' => 'Consultation Pricing', 'route' => 'consultation-types.index', 'active' => 'consultation-types.*', 'roles' => ['admin']],
    ['label' => 'Appointments', 'route' => 'appointments.index', 'active' => 'appointments.*', 'roles' => ['admin', 'receptionist', 'doctor']],
    ['label' => 'A&E', 'route' => 'emergency.index', 'active' => 'emergency.*', 'roles' => ['admin', 'receptionist', 'nurse', 'doctor'], 'permission' => 'canAccessEmergency'],
    ['label' => 'Mortuary', 'route' => 'mortuary.index', 'active' => 'mortuary.*', 'roles' => ['admin', 'mortuary_attendant'], 'permission' => 'canAccessMortuary'],
    ['label' => 'My Availability', 'route' => 'doctor.availability.index', 'active' => 'doctor.availability.*', 'roles' => ['doctor']],
    ['label' => 'Triage',       'route' => 'triage.index',        'active' => 'triage.*',        'roles' => ['admin', 'nurse']],
    ['label' => 'Medication administration', 'route' => 'nursing.administrations.index', 'active' => 'nursing.administrations.*', 'roles' => ['admin', 'nurse']],
    ['label' => 'Staff Duty Roster', 'route' => 'nursing.roster.index', 'active' => 'nursing.roster.*', 'roles' => ['admin', 'receptionist', 'nurse', 'chief_nurse', 'doctor', 'lab', 'pharmacist', 'chief_pharmacist', 'accountant', 'chief_accountant', 'mortuary_attendant']],
    ['label' => 'Admissions', 'route' => 'admissions.index', 'active' => 'admissions.*', 'roles' => ['admin', 'doctor', 'nurse']],
    ['label' => 'Consultation', 'route' => 'consultations.index', 'active' => ['consultations.index', 'consultations.show', 'consultations.start', 'consultations.update', 'consultations.complete', 'consultations.prescriptions.*', 'consultations.lab-requests.*'], 'roles' => ['doctor']],
    ['label' => 'My consultation history', 'route' => 'consultations.history.index', 'active' => ['consultations.history.index', 'consultations.history'], 'roles' => ['doctor']],
    // ['label' => 'Laboratory', 'route' => 'lab.index',      'active' => 'lab.*',      'roles' => ['admin', 'lab', 'doctor']],
    ['label' => 'Laboratory', 'route' => 'lab.index', 'active' => ['lab.index', 'lab.show', 'lab.report', 'lab.tests.*', 'lab.attachments.*'], 'roles' => ['admin', 'lab', 'doctor']],
    ['label' => 'External Lab Request', 'route' => 'lab.referrals.create', 'active' => 'lab.referrals.*', 'roles' => ['admin', 'receptionist', 'lab']],
    ['label' => 'Pharmacy',   'route' => 'pharmacy.index', 'active' => 'pharmacy.*', 'roles' => ['admin', 'pharmacist', 'chief_pharmacist']],
    ['label' => 'Billing', 'route' => 'billing.index', 'active' => 'billing.*', 'roles' => ['admin', 'accountant', 'receptionist']],
    ['label' => 'Finance overview', 'route' => 'finance.index', 'active' => 'finance.*', 'roles' => ['admin', 'chief_accountant']],
    ['label' => 'Hospital Settings', 'route' => 'settings.edit', 'active' => 'settings.*', 'roles' => ['admin']],

];
