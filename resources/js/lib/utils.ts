import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Merges conditional class names and resolves Tailwind conflicts.
 *
 * @example
 * cn('px-2', isActive && 'bg-accent', 'px-4'); // => 'bg-accent px-4'
 */
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}
