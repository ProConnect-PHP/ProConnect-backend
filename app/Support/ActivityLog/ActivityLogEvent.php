<?php

namespace App\Support\ActivityLog;

enum ActivityLogEvent: string
{
    case AuthLoginSuccess = 'auth.login.success';
    case AuthLoginFailed = 'auth.login.failed';
    case AuthLogout = 'auth.logout';
    case AuthRefreshFailed = 'auth.refresh.failed';
    case OAuthLoginSuccess = 'auth.oauth.login.success';
    case OAuthLoginFailed = 'auth.oauth.login.failed';
    case OAuthAccountLinked = 'auth.oauth.account_linked';

    case ProfessionalProfileCreated = 'professional_profile.created';
    case ProfessionalProfileUpdated = 'professional_profile.updated';

    case ServiceCreated = 'service.created';
    case ServiceUpdated = 'service.updated';
    case ServiceDeleted = 'service.deleted';

    case AvailabilityCreated = 'availability.created';
    case AvailabilityUpdated = 'availability.updated';
    case AvailabilityDeleted = 'availability.deleted';
    case AvailabilityExceptionCreated = 'availability_exception.created';
    case AvailabilityExceptionUpdated = 'availability_exception.updated';
    case AvailabilityExceptionDeleted = 'availability_exception.deleted';

    case BookingCreated = 'booking.created';
    case BookingConfirmed = 'booking.confirmed';
    case BookingPaid = 'booking.paid';
    case BookingCancelled = 'booking.cancelled';
    case BookingRescheduled = 'booking.rescheduled';
    case BookingStarted = 'booking.started';
    case BookingFinished = 'booking.finished';
    case BookingNoShow = 'booking.no_show';
    case BookingConflictDetected = 'booking.conflict_detected';

    case PackageProductCreated = 'package_product.created';
    case PackageProductUpdated = 'package_product.updated';
    case PackageProductDeleted = 'package_product.deleted';
    case PackagePurchased = 'package.purchased';
    case PackageSessionReserved = 'package.session_reserved';
    case PackageSessionConsumed = 'package.session_consumed';

    case PaymentCreated = 'payment.intent_created';
    case PaymentCheckoutCreated = 'payment.checkout_created';
    case PaymentApproved = 'payment.approved';
    case PaymentRejected = 'payment.rejected';
    case PaymentFailed = 'payment.failed';
    case PaymentRefunded = 'payment.refunded';
    case PaymentWebhookReceived = 'payment.webhook_received';
    case PaymentWebhookSignatureValid = 'payment.webhook_signature_valid';
    case PaymentWebhookSignatureInvalid = 'payment.webhook_signature_invalid';
    case PaymentWebhookProcessed = 'payment.webhook_processed';
    case PaymentWebhookDuplicated = 'payment.webhook_duplicated';
    case PaymentWebhookFailed = 'payment.webhook_failed';

    case VideoSessionCreated = 'video_session.created';
    case VideoSessionJoined = 'video_session.joined';
    case VideoSessionStarted = 'video_session.started';
    case VideoSessionClosed = 'video_session.closed';
    case VideoSessionTokenIssued = 'video_session.token_issued';

    case ReviewCreated = 'review.created';
    case ReviewUpdated = 'review.updated';
    case ReviewDeleted = 'review.deleted';
    case ReviewReplyCreated = 'review_reply.created';
    case ReviewReplyUpdated = 'review_reply.updated';

    case NotificationCreated = 'notification.created';
    case NotificationRead = 'notification.read';
    case NotificationDeleted = 'notification.deleted';
    case NotificationBroadcasted = 'notification.broadcasted';

    case AdminActivityLogViewed = 'admin.activity_log_viewed';

    case SecurityForbidden = 'security.forbidden';
    case SecurityRateLimited = 'security.rate_limited';
    case SecurityInvalidRole = 'security.invalid_role';
    case SecurityUnauthenticatedAccess = 'security.unauthenticated_access';
    case SystemError = 'system.error';
}
