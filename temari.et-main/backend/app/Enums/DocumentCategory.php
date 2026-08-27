<?php

namespace App\Enums;

/**
 * Category of a student/guardian document — the papers Ethiopian schools
 * actually collect at registration (birth certificate, ketebat/vaccination
 * card, kebele ID, Fayda…). Shared by student_attachments and
 * parent_attachments; nullable, "other" is the explicit catch-all.
 */
enum DocumentCategory: string
{
    case BirthCertificate = 'birth_certificate';
    case VaccinationCard = 'vaccination_card';
    case KebeleId = 'kebele_id';
    case NationalId = 'national_id';
    case Passport = 'passport';
    case TransferCertificate = 'transfer_certificate';
    case ReportCard = 'report_card';
    case MedicalCertificate = 'medical_certificate';
    case CustodyLetter = 'custody_letter';
    case Photograph = 'photograph';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BirthCertificate => 'Birth certificate',
            self::VaccinationCard => 'Vaccination card (ketebat)',
            self::KebeleId => 'Kebele ID',
            self::NationalId => 'National ID (Fayda)',
            self::Passport => 'Passport',
            self::TransferCertificate => 'Transfer certificate',
            self::ReportCard => 'Report card / transcript',
            self::MedicalCertificate => 'Medical certificate',
            self::CustodyLetter => 'Custody / guardianship letter',
            self::Photograph => 'Photograph',
            self::Other => 'Other',
        };
    }
}
