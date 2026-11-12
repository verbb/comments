<?php
namespace verbb\comments\mcp;

/** Only validated, public argument errors should be returned as JSON-RPC invalid params. */
class InvalidParamsException extends \InvalidArgumentException
{
}
