$env:GIT_MASTER='1'
Set-Location -LiteralPath 'C:\Users\Administrador\Desktop\Projetos\Protocolo'
git status --short
git add front/dashboard.php front/pasta.php front/termo.php src/Pasta.php src/Install.php public/js/app.js public/css/protocolo-modern.css ajax/version.php ajax/retirada_save.php ajax/view.php ajax/recebedor.php setup.php src/Recebedor.php front/recebedor.php front/recebedor.form.php
git commit -m "feat: catalogo de recebedores, dropdown com cadastro e mascara CPF/RG com teclado; fix 500 em minhas pastas"
git push origin main
