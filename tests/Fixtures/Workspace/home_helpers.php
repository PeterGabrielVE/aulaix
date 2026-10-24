<?php

use Illuminate\Support\Facades\DB;

/*
 * Helpers shared by the tests of the home screens: rows of the open
 * institution, inserted straight into the database.
 */

function seedStudent(int $institutionId, string $identification, string $status = 'active', string $name = 'Ana'): int
{
    return DB::table('students')->insertGetId([
        'institution_id' => $institutionId, 'given_names' => $name, 'family_names' => 'Pérez', 'sex' => 'female',
        'birth_date' => '2018-03-04', 'nationality' => 'V', 'identification_type' => 'school_id', 'identification' => $identification,
        'allergies' => 'Penicilina', 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function seedGuardian(int $institutionId, ?int $accountId, string $document): int
{
    return DB::table('guardians')->insertGetId([
        'institution_id' => $institutionId, 'given_names' => 'María', 'family_names' => 'Mora', 'document_type' => 'national_id',
        'document' => $document, 'phone' => '0414', 'account_id' => $accountId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function seedGuardianship(int $institutionId, int $studentId, int $guardianId): void
{
    DB::table('guardianships')->insert([
        'institution_id' => $institutionId, 'student_id' => $studentId, 'guardian_id' => $guardianId,
        'relationship' => 'mother', 'is_legal' => true, 'is_primary' => true,
    ]);
}

function seedCorrection(int $institutionId, int $guardianId, ?int $studentId, string $status = 'pending'): void
{
    DB::table('correction_requests')->insert([
        'institution_id' => $institutionId, 'guardian_id' => $guardianId, 'student_id' => $studentId, 'text' => 'Corregir la fecha',
        'status' => $status, 'submitted_at' => now(),
    ]);
}

function seedInvitation(int $institutionId, string $email, DateTimeInterface $expiresAt): void
{
    DB::table('invitations')->insert([
        'institution_id' => $institutionId, 'email' => $email, 'name' => 'Invitado', 'roles' => json_encode(['Docente']),
        'token_hash' => hash('sha256', $email), 'expires_at' => $expiresAt, 'status' => 'pending',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}
