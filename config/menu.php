<?php

return [
    ['label' => 'Dashboard',    'route' => 'dashboard', 'roles' => '*'],
    ['label' => 'Patients',     'route' => 'patients.index',     'active' => 'patients.*',     'roles' => ['admin', 'receptionist', 'nurse', 'doctor']],
    ['label' => 'Staff',             'route' => 'staff.index',   'active' => 'staff.*',    'roles' => ['admin', 'chief_accountant']],
    ['label' => 'Departments', 'route' => 'departments.index', 'active' => 'departments.*', 'roles' => ['admin']],
    ['label' => 'Consultation Pricing', 'route' => 'consultation-types.index', 'active' => 'consultation-types.*', 'roles' => ['admin']],
    ['label' => 'Appointments', 'route' => 'appointments.index', 'active' => 'appointments.*', 'roles' => ['admin', 'receptionist', 'doctor'], 'module' => 'appointments'],
    ['label' => 'A&E', 'route' => 'emergency.index', 'active' => ['emergency.index', 'emergency.store', 'emergency.unidentified.store', 'emergency.brought-in-dead.*', 'emergency.notes.store', 'emergency.mortuary.receive', 'emergency.items-used.store'], 'roles' => ['admin', 'receptionist', 'nurse', 'doctor'], 'permission' => 'canAccessEmergency', 'module' => 'emergency'],
    ['label' => 'A&E Pharmacy', 'route' => 'emergency.pharmacy.index', 'active' => 'emergency.pharmacy.*', 'roles' => ['admin', 'receptionist', 'pharmacist', 'chief_pharmacist', 'nurse', 'doctor'], 'permission' => 'canAccessEmergencyPharmacy', 'modules' => ['emergency', 'pharmacy', 'emergency_pharmacy']],
    ['label' => 'Mortuary', 'route' => 'mortuary.index', 'active' => 'mortuary.*', 'roles' => ['admin', 'mortuary_attendant'], 'permission' => 'canAccessMortuary', 'module' => 'mortuary'],
    ['label' => 'My Availability', 'route' => 'doctor.availability.index', 'active' => 'doctor.availability.*', 'roles' => ['doctor'], 'module' => 'appointments'],
    ['label' => 'Triage',       'route' => 'triage.index',        'active' => 'triage.*',        'roles' => ['admin', 'nurse']],
    ['label' => 'Medication administration', 'route' => 'nursing.administrations.index', 'active' => 'nursing.administrations.*', 'roles' => ['admin', 'nurse'], 'module' => 'medication_administration'],
    ['label' => 'Staff Duty Roster', 'route' => 'nursing.roster.index', 'active' => 'nursing.roster.*', 'roles' => ['admin', 'receptionist', 'nurse', 'chief_nurse', 'doctor', 'lab', 'pharmacist', 'chief_pharmacist', 'accountant', 'chief_accountant', 'mortuary_attendant'], 'module' => 'staff_roster'],
    ['label' => 'Admissions', 'route' => 'admissions.index', 'active' => 'admissions.*', 'roles' => ['admin', 'doctor', 'nurse'], 'module' => 'inpatient'],
    ['label' => 'Consultation', 'route' => 'consultations.index', 'active' => ['consultations.index', 'consultations.show', 'consultations.start', 'consultations.update', 'consultations.complete', 'consultations.prescriptions.*', 'consultations.lab-requests.*'], 'roles' => ['doctor']],
    ['label' => 'My consultation history', 'route' => 'consultations.history.index', 'active' => ['consultations.history.index', 'consultations.history'], 'roles' => ['doctor']],
    // ['label' => 'Laboratory', 'route' => 'lab.index',      'active' => 'lab.*',      'roles' => ['admin', 'lab', 'doctor']],
    ['label' => 'Laboratory', 'route' => 'lab.index', 'active' => ['lab.index', 'lab.show', 'lab.report', 'lab.tests.*', 'lab.attachments.*'], 'roles' => ['admin', 'lab', 'doctor'], 'module' => 'laboratory'],
    ['label' => 'External Lab Request', 'route' => 'lab.referrals.create', 'active' => 'lab.referrals.*', 'roles' => ['admin', 'receptionist', 'lab'], 'modules' => ['laboratory', 'external_lab']],
    ['label' => 'Pharmacy',   'route' => 'pharmacy.index', 'active' => 'pharmacy.*', 'roles' => ['admin', 'pharmacist', 'chief_pharmacist'], 'module' => 'pharmacy'],
    ['label' => 'Billing', 'route' => 'billing.index', 'active' => 'billing.*', 'roles' => ['admin', 'accountant', 'receptionist']],
    ['label' => 'Finance overview', 'route' => 'finance.index', 'active' => 'finance.*', 'roles' => ['admin', 'chief_accountant'], 'module' => 'finance_reports'],
    ['label' => 'Hospital Settings', 'route' => 'settings.edit', 'active' => 'settings.*', 'roles' => ['admin']],

];
