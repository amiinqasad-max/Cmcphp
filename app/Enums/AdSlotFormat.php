<?php

namespace App\Enums;

enum AdSlotFormat: string
{
    case Auto = 'auto';
    case Horizontal = 'horizontal';
    case Vertical = 'vertical';
    case Rectangle = 'rectangle';
    case Fluid = 'fluid';
}
