<?php

use Laravel\Mcp\Facades\Mcp;

it('registers the mcp server under the configured handle when local', function (): void {
    expect(Mcp::getLocalServer('translator'))->not->toBeNull();
});
