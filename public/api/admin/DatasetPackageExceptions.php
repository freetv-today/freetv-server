<?php

declare(strict_types=1);

namespace FreeTV\Admin;

class DatasetPackageAvailabilityException extends \RuntimeException
{
}

class DatasetPackageMetadataAvailabilityException extends DatasetPackageAvailabilityException
{
}

class DatasetPackageMetadataIntegrityException extends \RuntimeException
{
}

class DatasetPackageIntegrityException extends \RuntimeException
{
}
