# CloudCheats — instalação na máquina virtual

O repositório já contém o add-on em:

`upload/src/addons/XenSoluce/InviteSystem`

O identificador técnico original foi mantido para preservar a compatibilidade com as classes, tabelas e atualizações do add-on. Toda a marca apresentada ao utilizador foi alterada para **CloudCheats**.

## Publicação

1. Envie o conteúdo da pasta `upload/` para a raiz pública da instalação XenForo na máquina virtual.
2. No painel administrativo, abra **Add-ons**.
3. Instale **CloudCheats Invite System**. Se uma versão antiga já estiver instalada, execute a atualização/reconstrução do add-on.
4. Em **Setup > Options > Basic board information**, defina:
   - Board title: `CloudCheats`
   - Board short title: `CloudCheats`
5. Em **Setup > Options > CloudCheats Invite System**, escolha se o código de convite é obrigatório e configure os grupos autorizados.
6. Em **Groups & permissions**, conceda as permissões do CloudCheats Invite System aos grupos que podem criar convites.
7. Limpe/reconstrua o cache de templates após a atualização, caso o tema não apareça imediatamente.

## Tema incluído

O template `cloudcheats_theme.less` é carregado automaticamente pelo add-on. Ele aplica o visual escuro moderno inspirado nos anexos às páginas do fórum, autenticação, registo, membros e sistema de convites. O cabeçalho usa a marca textual CloudCheats sem depender de um ficheiro de logótipo externo.

## Compatibilidade

Esta adaptação requer XenForo 2.3.4 ou superior. Os controladores e serviços do add-on foram atualizados para os nomes usados pela série 2.3.
