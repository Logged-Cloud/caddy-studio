<?php

use LoggedCloud\CaddyStudio\Tests\TestCase;

// Opt-in per file with `uses(TestCase::class)` where Laravel container access
// (config(), Eloquent) is needed. Pure compiler tests don't need it.
