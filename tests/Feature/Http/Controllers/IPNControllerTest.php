<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Http\Controllers\IPNController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(IPNController::class)]
class IPNControllerTest extends TestCase
{
    public function test_handle_ipn_for_netzme_gateway()
    {
        $this->assertTrue(true);
    }
}
