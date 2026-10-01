param([Parameter(Mandatory=$true)][string]$Root,[Parameter(Mandatory=$true)][string]$Php,
      [Parameter(Mandatory=$true)][string]$InstallerAccount)
$ErrorActionPreference='Stop'
# Explicit one-time administrator action, never invoked by the website.
if(-not(Test-Path -LiteralPath "$Root/installer/layout.json") -or -not(Test-Path -LiteralPath $Php)){throw 'Paths invalid'}
$credential=Get-Credential -UserName $InstallerAccount -Message 'Akun pendamping Finance (bukan Administrator)'
foreach($mode in @('run','sync')) {
  $name='Finance-'+$mode+'-'+([Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($Root)) -replace '[^a-zA-Z0-9]','')
  $action=New-ScheduledTaskAction -Execute $Php -Argument ('"'+$Root+'\tools\install\portable\finance_setup.php" '+$mode)
  $trigger=New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes $(if($mode -eq 'run'){1}else{5}))
  $settings=New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 15)
  # Limited, not Highest; no password is written to a script/config or output.
  Register-ScheduledTask -TaskName $name -Action $action -Trigger $trigger -Settings $settings -User $InstallerAccount -Password $credential.GetNetworkCredential().Password -RunLevel Limited | Out-Null
}
