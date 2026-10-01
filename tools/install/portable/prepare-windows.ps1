param([Parameter(Mandatory=$true)][string]$Root,[Parameter(Mandatory=$true)][string]$InstallerAccount,
      [Parameter(Mandatory=$true)][string]$WebAccount)
$ErrorActionPreference='Stop'
# Administrative provisioning only. The PHP site and scheduled companion must use separate non-admin accounts.
$item=Get-Item -LiteralPath $Root -Force
if ($item.FullName -eq [IO.Path]::GetPathRoot($item.FullName) -or -not (Test-Path -LiteralPath "$Root/installer/layout.json") -or (Test-Path -LiteralPath "$Root/config/customer.json")) { throw 'New v6 package required' }
$owner=(New-Object System.Security.Principal.NTAccount($InstallerAccount)).Translate([System.Security.Principal.SecurityIdentifier])
$web=(New-Object System.Security.Principal.NTAccount($WebAccount)).Translate([System.Security.Principal.SecurityIdentifier])
if ($owner.Value -eq $web.Value -or $owner.Value -in @('S-1-5-18','S-1-5-32-544') -or $web.Value -in @('S-1-5-18','S-1-5-32-544')) { throw 'Separate non-admin accounts required' }
$all=@($item)+@(Get-ChildItem -LiteralPath $Root -Force -Recurse)
foreach ($p in $all) { if (($p.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Reparse points rejected' } }
foreach ($relative in @('private','private/agent','private/delivery','storage','storage/license','storage/setup','storage/inbox','storage/sessions','storage/logs','storage/cache','public/uploads','public/assets/uploads')) {
  [IO.Directory]::CreateDirectory((Join-Path $Root $relative)) | Out-Null
}
function Set-FinanceAcl([string]$Path,[bool]$Folder,[string]$WebRights) {
  $acl=if($Folder){New-Object System.Security.AccessControl.DirectorySecurity}else{New-Object System.Security.AccessControl.FileSecurity}
  $acl.SetAccessRuleProtection($true,$false);$acl.SetOwner($owner)
  $inherit=if($Folder){[System.Security.AccessControl.InheritanceFlags]'ContainerInherit,ObjectInherit'}else{[System.Security.AccessControl.InheritanceFlags]::None}
  foreach($sid in @($owner,(New-Object System.Security.Principal.SecurityIdentifier('S-1-5-18')),(New-Object System.Security.Principal.SecurityIdentifier('S-1-5-32-544')))) {
    $acl.AddAccessRule((New-Object System.Security.AccessControl.FileSystemAccessRule($sid,'FullControl',$inherit,'None','Allow')))
  }
  if($WebRights -ne ''){$acl.AddAccessRule((New-Object System.Security.AccessControl.FileSystemAccessRule($web,$WebRights,$inherit,'None','Allow')))}
  Set-Acl -LiteralPath $Path -AclObject $acl
}
$all=@(Get-Item -LiteralPath $Root)+@(Get-ChildItem -LiteralPath $Root -Force -Recurse)
foreach($p in $all){
  $rel=$p.FullName.Substring($item.FullName.Length).TrimStart('\','/').Replace('\','/')
  $rights='ReadAndExecute'
  if($rel -eq 'private' -or $rel.StartsWith('private/')){$rights=''}
  if($rel -match '^(storage/(inbox|sessions|logs|cache)|public/(uploads|assets/uploads))(/|$)'){$rights='Modify'}
  Set-FinanceAcl $p.FullName $p.PSIsContainer $rights
}
Write-Output 'ACL prepared. Run PHP companion with InstallerAccount, not Administrator. Windows acceptance remains pending real-host tests.'
