import re, json, requests

from unidecode import unidecode
from PyPDF2 import PdfReader

def removeCaracteres(text):
    remover = r'[./-]'

    novaString = unidecode(text.upper())
    #novaString = re.sub(' ', '', novaString)
    novaString = re.sub(remover, '', novaString)
    novaString = re.sub('\n', '', novaString)

    return novaString

def consultaDiario(textoDiario, clientes):

    diario = removeCaracteres(textoDiario)
    empresaMencionada = []

    try:
        try:
            for cliente in clientes:
                cliente_separado = cliente.split(";")
            
                cnpj = removeCaracteres(cliente_separado[1])
                razaoSocial = removeCaracteres(cliente_separado[2])
                razaoSocialJunta = cliente_separado[2]
                nomeFantasia = removeCaracteres(cliente_separado[3])
                inscricaoEstadual = removeCaracteres(cliente_separado[4])

                if cnpj in diario and razaoSocial not in empresaMencionada:
                    empresaMencionada.append(razaoSocial)
                    print("Consta no diário a menção ao cliente:",
                        razaoSocialJunta, ":", cnpj)
                    pass 

                if len(inscricaoEstadual)>2 and inscricaoEstadual in diario and razaoSocial not in empresaMencionada:
                    empresaMencionada.append(razaoSocial)
                    print("Consta no diário a menção ao cliente:",
                        razaoSocialJunta, ":", inscricaoEstadual)
                    pass

                if razaoSocial in diario and razaoSocial not in empresaMencionada:
                    empresaMencionada.append(razaoSocial)
                    print("Consta no diário a menção ao cliente:",
                        razaoSocialJunta)
                    pass

                if nomeFantasia in diario and razaoSocial not in empresaMencionada:
                    empresaMencionada.append(razaoSocial)
                    print("Consta no diário a menção ao cliente:",
                        razaoSocialJunta, ":", nomeFantasia)
                    pass

        except Exception as e:
            print("Não há menções no diário.", e)
    except Exception as e:
        print("\nNão há clientes na base de dados")


def cadastro(listaClientes):
    cnpj = input("Informe o CNPJ: ")
    razaoSocial = input("Informe o Razão Social: ").upper()
    nomeFantasia = input("Informe o Nome Fantasia: ").upper()
    inscricaoEstadual = input("Informe a inscrição estadual: ")

    try:
        if len(cnpj)>0 and len(razaoSocial) > 0:
            try:
                ultima_linha = listaClientes.readlines()[-1]
                ultima_linha = ultima_linha.split(';')
                idUltimoCadastro = int(ultima_linha[0])
            except IndexError:
                idUltimoCadastro = 0

            novoCliente = f"{idUltimoCadastro + 1};{cnpj};{razaoSocial};{nomeFantasia};{inscricaoEstadual}\n"

            listaClientes.write(novoCliente)

            print("\nDados inseridos")
        else:
            print("\nNecessário preencher CNPJ e Razão Social ao menos.")
    except:
        print("\nNecessário preencher CNPJ e Razão Social ao menos.")


def exibeCadastros(listaClientes):
    print("\n\n## Lista de Clientes ##")

    for cliente in listaClientes:
        cliente = cliente.strip().split(';')
        print('ID:', cliente[0], '|', cliente[2], '| CNPJ:', cliente[1])


def removeCadastro(listaClientes):
    idCliente = int(input(
        "\nInforme o ID para exclusão, caso não saiba, digite 0 para ver a lista: "))

    if idCliente == 0:
        exibeCadastros(listaClientes)
    else:
        novaListaClientes = []
        for cliente in listaClientes:
            cliente = cliente.strip().split(';')
            if int(cliente[0]) == idCliente:
                print("O cadastro de ID:", idCliente, "foi removido")
            else:
                novaListaClientes.append(','.join(cliente))

        listaClientes.seek(0)

        listaClientes.truncate()

        novoID = 1
        for cliente in novaListaClientes:
            partes = cliente.split(';', 1)
            cliente = f"{novoID},{partes[1]}"
            novoID += 1
            listaClientes.write(cliente + "\n")

        exibeCadastros(listaClientes)


def menu(textoDOE):
    while True:
        try:
            with open("clientes.csv", 'r+', encoding='utf8') as baseClientes:
                opcao = int(input(
                    """ 
## Consulta DOE AL ##

Selecione uma opção:

[1] - Consultar Diário Oficial de Alagoas
[2] - Cadastrar novo cliente
[3] - Listar clientes cadastrados
[4] - Remover cliente cadastrado
[5] - Sair do sistema

: """))

                if opcao == 1:
                    consultaDiario(textoDOE, baseClientes)

                elif opcao == 2:
                    cadastro(baseClientes)
                elif opcao == 3:
                    exibeCadastros(baseClientes)
                elif opcao == 4:
                    removeCadastro(baseClientes)
                elif opcao == 5:
                    exit()
                else:
                    print("Opção inválida. Digite um número válido.")
        except ValueError:
            print("Digite um número válido!")
        except FileNotFoundError:
            print("Arquivo de dados de cliente não encontrado.")
            break

textoDoe = ""

#Lendo Diário do dia diretamente do arquivo txt
with open("diario.txt", 'r+', encoding='utf8') as textoDiario:
    textoDoe = textoDiario.read()

#Lendo diário do arquivo PDF
""" arquivoPDF = PdfReader("diario.pdf")
numeroPaginas = len(arquivoPDF.pages)

print("Aguarde enquanto o arquivo está sendo lido...")
for page in range(numeroPaginas):
    getPage = arquivoPDF.pages[page]
    textPage = getPage.extract_text()
    textoDoe = textoDoe + textPage """

menu(textoDoe)