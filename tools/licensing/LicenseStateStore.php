<?php
declare(strict_types=1);
interface LicenseStateStore
{
    public function exists(string $name): bool;
    public function read(string $name, int $mode): array;
    public function write(string $name, array $value, int $mode): void;
    public function lock();
}
