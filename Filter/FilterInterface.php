<?php

// src/Rapsys/PackBundle/Twig/Filter/FilterInterface.php
namespace Rapsys\PackBundle\Twig\Filter;

interface FilterInterface {
    //TODO: see if we need something else (like a constructor that read parameters or something else ?)
    public function process($content);
}
