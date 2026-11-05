# Suítes Crepúsculo

Site: https://suitescrepusculo.com.br/

Repositório próprio do código e dos recursos do projeto. A administração da VPS continua exclusivamente pelo runner existente `gerente-vps`, no repositório `superamplitude/Gerente-VPS`. Os scripts deste projeto são executados por referências imutáveis e SHA-256 conferido; não há outro runner.

## Publicado

- Entrada PHP reduzida de 206276 para 9503 bytes; 53 módulos separados em `pages/`, preservando endereços, bootstrap, autenticação e permissões. Alguns conteúdos relacionados continuam agrupados no mesmo módulo.
- Mapa: cadastro de reserva, nova unidade, acomodações, lista de reservas e configuração da automação abrem em janelas sobrepostas. Botões e campos compactos, com verificação em desktop e celular.
- Painel: voz natural pela Central de Comunicação existente, com perfil Callirrhoe / gemini-3.8-live herdado do CRM, saudação, pergunta por texto, microfone quando suportado pelo navegador, repetição e interrupção. A reprodução automática está sujeita à política de áudio do navegador.
- Respostas usam a IA e a base de conhecimento já existentes na pousada.
- Adaptador WhatsApp conectado ao gateway existente em `http://127.0.0.1:18180`, substituindo o endereço antigo 18080. Credenciais ficam fora da raiz pública.
- Código PHP, JS e CSS atuais versionados aqui, junto a 36 imagens originais transferidas com os mesmos hashes Git. Os 49 arquivos específicos do Crepúsculo foram retirados da branch atual do Gerente.

## Evidências

| Verificação | Resultado |
| --- | --- |
| Separação de páginas | [Execução 42100299414](https://github.com/superamplitude/Gerente-VPS/actions/runs/42100299414), sintaxe PHP e HTTP aprovados |
| Janelas do mapa | [Execução 42101988824](https://github.com/superamplitude/Gerente-VPS/actions/runs/42101988824), cinco ações em sobreposição, celular e zero erros JS |
| Voz e IA | [Execução 42104152952](https://github.com/superamplitude/Gerente-VPS/actions/runs/42104152952), WAV real de 282596 bytes, reprodução iniciada e resposta real da IA |
| Autenticação da voz | Sem sessão: HTTP401; CSRF inválido: HTTP419 |
| Gateway WhatsApp | [Execução 42104889724](https://github.com/superamplitude/Gerente-VPS/actions/runs/42104889724), acesso confirmado como usuário Linux do site |
| QR Code real | [Execução 42105943746](https://github.com/superamplitude/Gerente-VPS/actions/runs/42105943746), payload gerado, sem mensagens ou ligações externas |

O manifesto em `deploy/runtime-manifest-20261105.json` registra os hashes do código publicado. Configuração de produção, chaves, banco e dados privados não estão no repositório.

## Pendente para atendimento completo

O número WhatsApp ainda precisa ser pareado no módulo WhatsApp, pelo botão **Gerar QR Code**. Recepção/resposta automática de mensagens e atendimento automático de ligações no Crepúsculo ainda não foram concluídos nem homologados. O pareamento por QR Code não comprova essas funções.

O diretório `integrations/attendance` contém um protótipo anterior não instalado. A voz publicada usa os controladores PHP em `app/` e a Central de Comunicação existente; esse protótipo não é outro serviço em execução.

Nenhum backup local foi criado, nenhum backup original foi utilizado ou alterado, e os runtimes CRM/AEL não foram reiniciados.
