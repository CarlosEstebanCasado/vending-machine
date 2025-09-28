$hostEntry = "127.0.0.1`t vendingmachine.test"
$hostsPath = "$env:SystemRoot\System32\drivers\etc\hosts"

if (Select-String -Path $hostsPath -Pattern "vendingmachine\.test" -Quiet) {
    Write-Output "Entry already present in $hostsPath"
    exit 0
}

Write-Output "Adding host entry to $hostsPath (requires elevated PowerShell)..."
Add-Content -Path $hostsPath -Value $hostEntry
Write-Output "Added: $hostEntry"
