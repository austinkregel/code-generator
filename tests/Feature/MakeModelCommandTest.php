<?php

test('make:model command test', function () {
    $this->artisan('make:model Item Inventory')
         ->expectsOutput('Simplicity is the ultimate sophistication.')
         ->assertExitCode(0);
});
