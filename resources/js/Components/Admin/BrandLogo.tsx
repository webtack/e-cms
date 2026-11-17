import { cn } from "@/lib/utils";
import logoDark from "../../../images/logo-mini.png";
import logoWhite from "../../../images/logo-mini-white.png";

type BrandLogoProps = {
    /** Extra classes applied to both images (for example a margin). */
    className?: string;
};

/**
 * The admin logo: the dark logo in the light theme and the white one in the dark theme. Both images are rendered and
 * switched with the `dark` class that `ThemeProvider` sets on `<html>`. The images are decorative (the site name is
 * shown next to them), so `alt` is empty. The files are bundled by Vite (`resources/images`), so a host needs no `public/images`. Shown at the original size of the files (119×32 px).
 *
 * @example
 * <BrandLogo className="mb-2" />
 */
export function BrandLogo({ className }: BrandLogoProps) {
    return (
        <>
            <img
                src={logoDark}
                alt=""
                width={82}
                height={22}
                className={cn("max-w-none dark:hidden", className)}
            />
            <img
                src={logoWhite}
                alt=""
                width={82}
                height={22}
                className={cn("hidden max-w-none dark:block", className)}
            />
        </>
    );
}
