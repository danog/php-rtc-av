<?php

namespace Tests\Webrtc\AVCodec;

use stdClass;

class Fraction
{
    private StdClass $fraction;

    /**
     * @param int $numerator
     * @param int $denominator
     */
    public function __construct(int $numerator, int $denominator)
    {
        $this->fraction = new StdClass();
        $this->fraction->num = $numerator;
        $this->fraction->den = $denominator;
    }

    public function __invoke()
    {
        return $this->fraction;
    }
}