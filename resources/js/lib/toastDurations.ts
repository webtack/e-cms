/**
 * Display time of admin toasts, in milliseconds. Sonner has one `duration`
 * per toast and no per-status default, so every `toast.success` /
 * `toast.error` call passes these constants.
 *
 * @example
 * toast.error(message, { duration: TOAST_ERROR_DURATION });
 */

/** A success toast stays visible for 4 seconds. */
export const TOAST_SUCCESS_DURATION = 4000;

/** An error toast stays visible for 8 seconds, so the message can be read. */
export const TOAST_ERROR_DURATION = 8000;
