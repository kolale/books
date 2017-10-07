<?php

namespace BookBundle\Twig;

class ImageResizeExtension extends \Twig_Extension
{
    public function getName()
    {
        return 'image_resize_extension';
    }

    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction(
                'image_resize',
                [$this, 'imageResizeFunction'],
                ['is_safe' => array('html'), 'needs_environment' => false]
            )
        ];
    }

    /**
     *
     * @param string $path
     * @param int $width
     * @param int $height
     * @return string
     */
    public function imageResizeFunction($path, $width, $height)
    {
        return '<img src="' . $path . '" width="' . $width . '" height="' . $height . '">';
    }
}
