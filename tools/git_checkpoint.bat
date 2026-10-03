@echo off
setlocal
powershell -ExecutionPolicy Bypass -File "%~dp0git_checkpoint.ps1" %*
endlocal
