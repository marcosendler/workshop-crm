Atue como um especialista em PRD focado em soluções MVP, vamos construir somente a descrição do projeto, depois vou criar o as historias de usuario, definição de banco de dados e fases do projeto, então foque exclusivamente na descrição do projeto e siga a estrutura predefinida abaixo. Vou escrever em portugues mas voce vai me dar o resultado em INGLES. a SAIDA DEVE ser em snippet markdown.



# Overview
Vamos constuir um MVP de um CRM para multiplas empresas,
onde o dono da empresa ira se cadastrar bem como cadastrar a empresa,
fazer o gerenciamento dos seus vendedores e cadastrar leads,
o cadastro de leads sera feito diretamente na tela de kanban do crm,
no qual vai ter um botao para adicionar os leads e vai abrir um modal que
tem a procura se o lead ja existe na base, se ja existir seleciona ele e
cria um novo deal, se nao existir ja cadastra por ali mesmo e depois cria um
deal pra ele, .

Os deals ficarao em um kanban com passos padroes (me de a sugestao)
sera fixo para todas as empresas do sistema. Ao clicar em um deal poderemos
fazer anotações alterar valores, e um botao para conversar com a pessoa,
ao clicar em conversar vai para uma tela de conversa que sera uma integração com
whatsapp via evolution api.

o Sistema tem um guia basico de design a ser seguido na pasta @docs/design

--- 

#Tech-stack
- Laravel 12
- PGsql 18
- Livewire v4 (user panel)
- MultiTenancy single database
- EvolutionAPI v2

---


## Users & Roles (RBAC)

**Business Owner (1–3 usuários por tenant):**  
Possui acesso completo a todos os dados dentro da sua própria empresa (tenant).  
Pode visualizar e gerenciar todos os leads, etapas do pipeline, vendedores, regras de atribuição, relatórios e exportações.  
Pode reatribuir leads entre vendedores e acessar dashboards globais de desempenho da empresa.

**Salesperson (5–50+ usuários por tenant):**  
Pode apenas visualizar e gerenciar os leads atribuídos a ele.  
Pode mover seus leads entre as etapas do pipeline, atualizar informações do cliente, fazer upload/visualizar documentos e interagir pela aba de WhatsApp nos leads sob sua responsabilidade.

---

## Core Workflows

Os leads devem ser unicos entre os tenants,
com regras forttes via banco de dados (unique com index combinado tenant_id e email
por exemplo)

O processo de cadastro, cadastra a empresa (apenas nome) e os dados do usuario,
esse usuario fica automaticamente marcado como dono da empresa e depois ele
faz os convites para novos usuarios (usuarios só podem ver outros usuarios
dentro da propria empresa).

O dono da empresa pode criar leads e selecionar um vendedor para ser o
dono daquele lead.

Um Lead pode ter multiplos deals, o dono do lead ja vira dono do deal respectivamente.

Os deals preciam ter titulo e valor, caso o deal seja perdido é necessario armazenar o motivo da perda.

Para o MVP toda parte de leads deve ser feito direto na tela do kanban.

O Vendedor pode ver somente os leads dele na tela de kanban,
na hora de criar um lead ele precisa ver se ja existe

O dono da empresa pode ver todos os leads

O dono da empresa precisa conectar o whatsapp via qr code na tela de configurações,
assim liberando o acesso a tela de whatsapp aos vendedores


---

Tech Requirements (open to your recommendation, but must be production-ready)

- **Autenticação segura + redefinição de senha**
    - Login em nível de produção com rate limit, segurança de sessão e fluxo de redefinição de senha.
    - Suporte a autenticação sensível a tenant (usuários pertencem a um tenant).

- **Multi-tenancy (banco único) com isolamento rigoroso de dados**
    - Todo registro deve estar associado a um `tenant_id`.
    - O isolamento de tenant deve ser garantido em código, não apenas por filtragem na interface.
    - Abordagem obrigatória: um trait compartilhado aplicado aos models que pertencem ao tenant que:
        - Aplique um **global scope** para filtrar automaticamente as queries por `tenant_id`.
        - Preencha automaticamente o `tenant_id` na criação.
        - Impeça acesso/atualização/exclusão entre tenants na camada de model/query.

- **RBAC aplicado no backend**
    - Permissões devem ser aplicadas no servidor para todas as ações (view/create/update/delete).
    - Vendedores só podem acessar leads/deals pertencentes ao seu tenant (e de acordo com as regras de ownership).
    - Donos do negócio podem acessar todos os dados dentro do seu tenant.

- **Interface amigável para mobile**
    - Interface responsiva para o fluxo do Vendedor (Kanban + detalhe do deal + aba de WhatsApp).
    - Otimizada para uso em celular (navegação rápida, cards legíveis, ações touch-friendly).