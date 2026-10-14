"""Consulta FTPS somente de leitura; credenciais permanecem fora do repositório."""
import ftplib
import json
import ssl
import sys

with open(sys.argv[1], encoding="utf-8-sig") as source:
    accounts = json.load(source)
host = "a16-asgard21.hospedagemuolhost.com.br"
for project, account in accounts.items():
    try:
        with ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=15) as ftp:
            ftp.connect(host, 21)
            ftp.auth()
            ftp.login(account["login"], account["password"])
            ftp.prot_p()
            ftp.cwd("/")
            names = {item.rstrip("/").rsplit("/", 1)[-1] for item in ftp.nlst()}
            required = {"public_html", "rumohub.rumoacamisa10.com.br"}
            print(f"{project}: FTPS/TLS autenticado; raízes disponíveis: {required.issubset(names)}; diretório FTP: {ftp.pwd()}")
    except Exception as error:
        # Não imprimir argumentos, respostas completas ou credenciais.
        code = str(error)[:3] if isinstance(error, ftplib.Error) else ""
        print(f"{project}: conexão não concluída ({type(error).__name__} {code}).")
