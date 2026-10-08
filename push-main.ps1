$env:GIT_MASTER='1'
Set-Location -LiteralPath 'C:\Users\Administrador\Desktop\Projetos\Protocolo'
git status --short
git add front/dashboard.php front/pasta.php front/termo.php src/Pasta.php src/Install.php public/js/app.js public/css/protocolo-modern.css ajax/version.php ajax/retirada_save.php ajax/view.php setup.php
git commit -m "feat: wizard nav picker escola recebido guiado historico e pos-registro"
git push origin main
