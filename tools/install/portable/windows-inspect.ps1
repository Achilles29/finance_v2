param([Parameter(Mandatory=$true)][string]$Root,[Parameter(Mandatory=$true)][string]$Target,
      [ValidateSet('yes','no')][string]$Secret='no',[ValidateSet('yes','no','worker')][string]$Web='no',
      [ValidateSet('yes','no')][string]$Runtime='no')
$ErrorActionPreference='Stop'
try {
  $rootItem=Get-Item -LiteralPath $Root -Force
  $targetItem=Get-Item -LiteralPath $Target -Force
  $owner=(Get-Acl -LiteralPath $rootItem.FullName).GetOwner([System.Security.Principal.SecurityIdentifier]).Value
  $identity=[System.Security.Principal.WindowsIdentity]::GetCurrent()
  $principal=New-Object System.Security.Principal.WindowsPrincipal($identity)
  if ($owner -in @('S-1-5-18','S-1-5-32-544')) { throw 'Dedicated owner required' }
  if ($Web -eq 'yes' -and ($identity.User.Value -eq $owner -or $principal.IsInRole([System.Security.Principal.WindowsBuiltInRole]::Administrator))) { throw 'Web separation required' }
  if ($Web -eq 'worker' -and ($identity.User.Value -ne $owner -or $principal.IsInRole([System.Security.Principal.WindowsBuiltInRole]::Administrator))) { throw 'Non-admin owner required' }
  if (-not $targetItem.FullName.StartsWith($rootItem.FullName+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase) -and $targetItem.FullName -ne $rootItem.FullName) { throw 'Outside root' }
  $item=$targetItem
  $runtimeWriters=@()
  if($Runtime -eq 'yes') {
    if($targetItem.FullName -notin @((Join-Path $rootItem.FullName 'storage\logs'),(Join-Path $rootItem.FullName 'storage\cache'),(Join-Path $rootItem.FullName 'storage\sessions'))) { throw 'Runtime path not allowed' }
    foreach($ace in (Get-Acl -LiteralPath $rootItem.FullName).GetAccessRules($true,$true,[System.Security.Principal.SecurityIdentifier])) {
      if($ace.AccessControlType -eq 'Allow' -and $ace.IdentityReference.Value -notin @('S-1-1-0','S-1-5-11','S-1-5-32-545')) {$runtimeWriters += $ace.IdentityReference.Value}
    }
  }
  $writeMask=[System.Security.AccessControl.FileSystemRights]::Write -bor [System.Security.AccessControl.FileSystemRights]::Delete -bor [System.Security.AccessControl.FileSystemRights]::ChangePermissions -bor [System.Security.AccessControl.FileSystemRights]::TakeOwnership
  while ($null -ne $item) {
    if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Reparse point rejected' }
    $acl=Get-Acl -LiteralPath $item.FullName
    $fileOwner=$acl.GetOwner([System.Security.Principal.SecurityIdentifier]).Value
    if ($fileOwner -notin @($owner,'S-1-5-18','S-1-5-32-544')) { throw 'Owner mismatch' }
    foreach ($ace in $acl.GetAccessRules($true,$true,[System.Security.Principal.SecurityIdentifier])) {
      if ($ace.AccessControlType -ne 'Allow') { continue }
      $sid=$ace.IdentityReference.Value
      $allowedWriters=@($owner,'S-1-5-18','S-1-5-32-544')
      if($Runtime -eq 'yes' -and $item.FullName -eq $targetItem.FullName){$allowedWriters += $runtimeWriters}
      if (($ace.FileSystemRights -band $writeMask) -ne 0 -and $sid -notin $allowedWriters) { throw 'Untrusted writer' }
      if ($Secret -eq 'yes' -and $item.FullName -eq $targetItem.FullName -and $sid -in @('S-1-1-0','S-1-5-11','S-1-5-32-545')) { throw 'Public secret reader' }
    }
    if ($item.FullName -eq $rootItem.FullName) { break }
    $item=Get-Item -LiteralPath (Split-Path -Parent $item.FullName) -Force
  }
  # Hard links are not accepted for private/config documents.
  if (-not $targetItem.PSIsContainer -and @(& fsutil.exe hardlink list $targetItem.FullName).Count -ne 1) { throw 'Hardlink validation failed' }
  'OK'
} catch { exit 1 }
