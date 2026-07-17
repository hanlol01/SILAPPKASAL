<?php

namespace App\Support;

final class ApiErrorCode
{
    public const ValidationFailed = 'validation_failed';
    public const Unauthenticated = 'unauthenticated';
    public const Forbidden = 'forbidden';
    public const TooManyRequests = 'too_many_requests';
    public const InvalidCredentials = 'invalid_credentials';
    public const AccountInactive = 'account_inactive';
    public const CurrentPasswordIncorrect = 'current_password_incorrect';
    public const RegistrationDuplicateActive = 'registration_duplicate_active';
    public const RegistrationDuplicatePending = 'registration_duplicate_pending';
    public const RegistrationInvalidCredentials = 'registration_invalid_credentials';
    public const RegistrationPasswordUnavailable = 'registration_password_unavailable';
    public const RegistrationNotPending = 'registration_not_pending';
    public const RegistrationNumberUnavailable = 'registration_number_unavailable';
    public const TrackingNotFound = 'tracking_not_found';
    public const PortalReportNotFound = 'portal_report_not_found';

    private function __construct()
    {
    }
}
