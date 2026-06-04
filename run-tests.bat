@echo off
REM ============================================================
REM  FinanceiroApp — Script de Testes
REM  Uso: run-tests.bat [suite] [filtro]
REM
REM  Exemplos:
REM    run-tests.bat              — Roda TODOS os testes
REM    run-tests.bat unit         — Roda apenas testes unitários
REM    run-tests.bat integration  — Roda apenas testes de integração
REM    run-tests.bat "" FunctionsTest  — Filtra por nome de classe
REM ============================================================

setlocal

set SUITE=%1
set FILTER=%2

cd /d "%~dp0"

echo.
echo  ======================================
echo   FinanceiroApp - Suite de Testes
echo  ======================================
echo.

REM Verificar se vendor existe
if not exist "vendor\bin\phpunit" (
    echo [ERRO] PHPUnit nao encontrado. Execute: composer install
    exit /b 1
)

REM Montar comando
set CMD=vendor\bin\phpunit --colors=always

if "%SUITE%"=="unit" (
    set CMD=%CMD% --testsuite Unit
    echo  Executando: Testes Unitarios
) else if "%SUITE%"=="integration" (
    set CMD=%CMD% --testsuite Integration
    echo  Executando: Testes de Integracao
) else (
    echo  Executando: Todos os Testes
)

if not "%FILTER%"=="" (
    set CMD=%CMD% --filter %FILTER%
    echo  Filtro: %FILTER%
)

echo.
echo  ----------------------------------------
echo.

%CMD%

echo.
echo  ========================================
echo   Testes finalizados.
echo  ========================================
echo.

endlocal
