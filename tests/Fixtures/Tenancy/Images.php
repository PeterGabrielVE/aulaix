<?php

namespace Tests\Fixtures\Tenancy;

use GdImage;

/**
 * Real and fake image contents for the upload tests.
 */
final class Images
{
    public static function png(): string
    {
        return self::render(fn (GdImage $image) => imagepng($image));
    }

    public static function jpeg(): string
    {
        return self::render(fn (GdImage $image) => imagejpeg($image));
    }

    public static function gif(): string
    {
        return self::render(fn (GdImage $image) => imagegif($image));
    }

    /**
     * Starts like a PNG but is not one: what a renamed script or a polyglot
     * looks like to a check that only reads the first bytes.
     */
    public static function pngHeaderOnly(): string
    {
        return "\x89PNG\r\n\x1a\n".str_repeat('<?php echo 1; ?>', 10);
    }

    /**
     * A PNG of just over 1 MB, padded after its end so it still decodes.
     */
    public static function pngOverOneMegabyte(): string
    {
        return self::png().str_repeat("\0", 1024 * 1024);
    }

    /**
     * @param  callable(GdImage): bool  $encode
     */
    private static function render(callable $encode): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        $encode($image);

        return (string) ob_get_clean();
    }
}
