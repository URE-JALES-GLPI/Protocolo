@echo off
REM Push do plugin Protocolo via Git Bash (duplo clique e pronto)
"C:\Program Files\Git\bin\bash.exe" -lc "cd '/c/Users/Administrador/Desktop/Projetos/Protocolo' && git status --short && git add -A && git commit -m 'update protocolo' && git push origin main"
pause
