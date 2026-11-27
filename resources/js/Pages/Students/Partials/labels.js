// Spanish labels for the codes the server sends (SPEC-009).
export const sexLabels = { female: 'Femenino', male: 'Masculino' };

export const relationshipLabels = { mother: 'Madre', father: 'Padre', other: 'Otro familiar' };

export const withdrawalReasons = {
    moved: 'Cambio de domicilio',
    changed_school: 'Cambio de plantel',
    economic_or_family: 'Razones económicas o familiares',
    other: 'Otro',
};

export const statusLabels = { active: 'Activo', withdrawn: 'Retirado' };

export const identificationLabels = { national_id: 'Cédula de identidad', school_id: 'Cédula escolar' };

export const day = (date) => (date ? date.split('-').reverse().join('/') : '');
