<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Prompt {

    /**
     * Constrói o prompt de CONTEÚDO conforme o template.
     * Assinatura compatível com o Generator:
     *   build(string $template, string $keyword, array $opts = [])
     */
    public static function build(string $template, string $keyword, string $url, array $opts = []) : string {
        switch ($template) {
            case 'review_roundup': return self::review_roundup($keyword, $opts);
            case 'review_single': return self::review_single($keyword, $opts, $url);
            case 'news':   return self::news($keyword, $opts);
            case 'howto':  return self::howto($keyword, $opts);
            case 'faq':  return self::faq($keyword, $opts);
            default:       return self::article($keyword, $opts);
        }
    }

    private static function review_roundup(string $kw, array $o) : string {
        $locale = $o['locale'] ?? 'pt_BR';
        $forced = trim((string)($o['forced_title'] ?? ''));
        $forcedLine = $forced ? "Use este TÍTULO final (não gere outro): \"{$forced}\".\n" : "";

        return <<<PROMPT
{$forcedLine}
Escreva um {$template} envolvente, com no minimo 2500 palavras, natural e humanizado em "{$locale}" sobre: "{$kw}".

Regras editoriais (não cite estas regras no texto):
- Introdução: primeira frase DEVE conter a palavra-chave principal de forma fluida e não forçada.
- Corpo em HTML SEM <h1> (use <h2>/<h3>/<p>/<ul>/<li>/<strong>/<em>...), linguagem fluida e prática.
- Faça mini-comparações se aplicavel entre os produtos, com prós e contras realistas (sem verdade absoluta), ou seja, nunca diga que produto é melhor do que outro, a não ser em comparativos, então nesse caso, seria algo como "produto x se destaca na bateria e produto y se destaca na distancia...".
- Repita a palavra-chave no primeiro H2 e naturalmente 1x por seção (variações semânticas ok).
- Conclusão: destacando os principais pontos dos prós e crontas, como mencionados acima no quesito de comparação. insira tbm a frase chave.
- Gere "meta_title" (≤ 60) e "meta_description" (≤ 155) com a keyword.
- Gere "image_alt" descritivo com a keyword (sem superlativos artificiais).
- Se possível, proponha 2 links internos (slugs) e 2 externos confiáveis em "links".

Aja como um redator profissional e crie um artigo review magnético, dinâmico e persuasivo. O texto deve:

Ter linguagem envolvente e convincente;

Incluir chamadas para ação (CTA) incentivando o leitor a visitar a página oficial do produto;

Deixe claro que o produto é vendido somente no site oficial;

Fazer uma análise completa sobre o produto existente na frase chave.

Incluir o links de afiliados no texto: sem link definido, então coloque links com #

Ser otimizado com técnicas de SEO para ranquear bem no Google.

Palavra chave para rankear: $kw
Lembre-se: você está escrevendo para pessoas do nível iniciante ao avançado, então mantenha um tom conversacional e um estilo de explicação excepcional, use palavra de transição do inicio ao fim do texto

Saída OBRIGATÓRIA: JSON UTF-8 **válido** no formato:
{
  "title": "{$forced}",
  "titles_suggestions": [],
  "content": "<h2>...</h2>...",
  "meta_title":"", "meta_description":"", "image_alt":"",
  "links":{"internal":[],"external":[]}
}
NÃO use markdown. NÃO inclua <h1> no content. NÃO use percentuais no corpo do texto.
PROMPT;
    }
    
private static function review_single(string $kw, array $o, string $url) : string {
        $locale = $o['locale'] ?? 'pt_BR';
        $forced = trim((string)($o['forced_title'] ?? ''));
        $forcedLine = $forced ? "Use este TÍTULO final (não gere outro): \"{$forced}\".\n" : "";

        return <<<PROMPT
{$forcedLine}
Escreva um {$template} envolvente com no minimo 1500 palavras, natural e humanizado em "{$locale}" sobre: "{$kw}".

Regras editoriais (não cite estas regras no texto):
- Introdução: primeira frase DEVE conter a palavra-chave principal de forma fluida e não forçada.
- Corpo em HTML SEM <h1> (use <h2>/<h3>/<p>/<ul>/<li>/<strong>/<em>...), linguagem fluida e prática.
- Estruture em seções se aplicavel (sem percentuais no texto):
  • Precisão do rastreamento e alcance
  • Bateria e recarga
  • Conforto e tamanho
  • Recursos extras
  • Facilidade de uso
- Faça mini-comparações se aplicavel entre os produtos (“os melhores”), com prós e contras realistas (sem verdade absoluta).
- Repita a palavra-chave no primeiro H2 e naturalmente 1x por seção (variações semânticas ok).
- Conclusão: “vale a pena?”, para quem é, e um CTA leve.
- Gere "meta_title" (≤ 60) e "meta_description" (≤ 155) com a keyword.
- Gere "image_alt" descritivo com a keyword (sem superlativos artificiais).
- Se possível, proponha 2 links internos (slugs) e 2 externos confiáveis em "links".

Aja como um redator profissional e crie um artigo review magnético, dinâmico e persuasivo. O texto deve:

Ter linguagem envolvente e convincente;

Incluir chamadas para ação (CTA) incentivando o leitor a visitar a página oficial do produto;

Deixe claro que o produto é vendido somente no site oficial;

Fazer uma análise completa sobre o produto ou {$url}, então acesse o site e tire as informações

Incluir o link de afiliado no texto: sem link definido, então coloque link com #

Ser otimizado com técnicas de SEO para ranquear bem no Google.

Palavra chave para rankear: $kw
Lembre-se: você está escrevendo para pessoas do nível iniciante ao avançado, então mantenha um tom conversacional e um estilo de explicação excepcional, use palavra de transição do inicio ao fim do texto

Saída OBRIGATÓRIA: JSON UTF-8 **válido** no formato:
{
  "title": "{$forced}",
  "titles_suggestions": [],
  "content": "<h2>...</h2>...",
  "meta_title":"", "meta_description":"", "image_alt":"",
  "links":{"internal":[],"external":[]}
}
NÃO use markdown. NÃO inclua <h1> no content. NÃO use percentuais no corpo do texto.
PROMPT;
    }

    private static function article(string $kw, array $o) : string {
        $locale = $o['locale'] ?? 'pt_BR';
        $forced = trim((string)($o['forced_title'] ?? ''));
        $forcedLine = $forced ? "Use este TÍTULO final (não gere outro): \"{$forced}\".\n" : "";

        return <<<PROMPT
{$forcedLine}
Escreva um ARTIGO com no minimo 1500 palavras, otimizado para Discover em "{$locale}" sobre: "{$kw}".

Atue como um especialista em SEO e um profundo conhecedor do título. Meu objetivo é criar um conteúdo superior ao dos meus concorrentes. O artigo deve ter excelente usabilidade e seguir rigorosamente as diretrizes do Google para alcançar a primeira posição nos resultados de busca. Vou enviar três artigos para análise. Por favor, aguarde enquanto envio cada um. Seu papel será identificar os pontos fortes de cada artigo e aguardar novas instruções antes de prosseguir. Apenas me deu um ok que guardou a informação desse texto, não crie nada agora!
Palavras primarias:
Palavras secundárias: 
OBJETIVO DO PROMPT
•	Criar um artigo que supere os concorrentes, com base nas diretrizes de SEO, GEO (Generative Engine Optimization) e AEO (Answer Engine Optimization).
•	Gerar um conteúdo humanizado, escaneável, semântico, original e otimizado para ranqueamento no Google e destaque em IAs generativas.


🔧 INSTRUÇÕES DETALHADAS PARA GERAÇÃO DO CONTEÚDO
🔁 Densidade e Repetição da Palavra-chave Primária
•	Estime a quantidade total de palavras do artigo.
•	Divida por 125 para obter a meta de repetições.
•	Distribua essas repetições de forma natural entre os blocos H2, garantindo presença no primeiro parágrafo de cada H2.
🧩 Estrutura de Escrita
H1 — Introdução Principal
•	Palavra-chave primária obrigatoriamente na primeira linha.
•	Parágrafos com 2 a 5 linhas cada.
•	Texto claro, natural, fluido e convidativo.
H2 — Blocos Principais
•	No mínimo 2 parágrafos por bloco.
•	Palavra-chave primária aparece no primeiro parágrafo sempre que possível.
•	Conteúdo profundo, escaneável e informativo.
H3 — Subtópicos e Perguntas (AEO)
•	1 ou 2 parágrafos de 2 a 4 linhas cada.
•	Tom de resposta objetiva e clara, ideal para snippets e IAs.
🧠 SEO SEMÂNTICO
Regras para Palavras-chave Secundárias
•	Cada termo deve aparecer pelo menos 2 vezes, distribuído em blocos diferentes.
•	Uso natural e contextualizado, com flexões e variações aceitas.
Palavras Semânticas
•	Extraia termos relevantes dos concorrentes (sem copiar frases).
•	Aplique ao longo do conteúdo com naturalidade e valor informativo.
Palavras LLM (Inteligência Semântica)
•	Produza variações naturais, evitando repetições mecânicas.
•	Adapte o tom para parecer uma resposta natural de IA (como ChatGPT, Gemini, Bard).
🎤 Tom de Voz e Estilo
•	Linguagem informacional, fluida, humanizada e escaneável.
•	Adote estilo didático, acolhedor e direto, com frases claras e bem conectadas.
•	Em conteúdos de review, escreva como um usuário que testou, usando provas sociais e copy persuasiva.
✍️ Boas Práticas de Escrita
•	Evite repetições mecânicas, jargões excessivos ou linguagem robótica.
•	Use bullet points com moderação (máx. 2 vezes por artigo) para organizar listas, instruções ou comparações.
•	Conclusão obrigatória: 1 a 2 parágrafos de até 4 linhas, com CTA.
•	Último H2 obrigatório: “Dúvidas Frequentes” com respostas curtas em bullet (250–300 caracteres por item).
🔒 Originalidade e Plágio Zero
•	Nenhum trecho dos concorrentes pode ser copiado.
•	Todo o texto deve ser original, fluido e parecer escrito por humano com domínio do tema.
🧠 Modelagem Estrutural
•	Analise os concorrentes e modele a estrutura (não o conteúdo).
•	Aprofunde tópicos superficiais, organize melhor e adicione exemplos únicos.
🧠 Ao Final, Gere Também:
🔹 Meta Tags
•	Meta Title: até 60 caracteres, com a palavra-chave primária.
•	Meta Description: até 155 caracteres, com CTA e palavra-chave primária.
🖼️ Imagem Realista para SEO Visual
•	Pessoa única, foco no rosto, fundo neutro e desfocado.
•	Estilo: hiper-realista, cinematográfico.
•	Proporção: 16:9 – resolução alta.
•	ALT da imagem deve conter a palavra-chave primária exata.

Saída: JSON UTF-8 (title, titles_suggestions[], content, meta_title, meta_description, image_alt, links{internal[],external[]}).
Sem markdown, sem <h1>.
PROMPT;
    }

    private static function news(string $kw, array $o) : string {
        $locale = $o['locale'] ?? 'pt_BR';
        $forced = trim((string)($o['forced_title'] ?? ''));
        $forcedLine = $forced ? "Use este TÍTULO final (não gere outro): \"{$forced}\".\n" : "";

        return <<<PROMPT
{$forcedLine}
Escreva uma NOTÍCIA factual em "{$locale}" sobre: "{$kw}", com lide claro.
Corpo em <h2>/<h3> SEM <h1>. Contexto, quem/onde/quando, impacto e próximos passos.
Meta_title/description curtas com a keyword.
Saída: JSON conforme schema.
PROMPT;
    }

    private static function howto(string $kw, array $o) : string {
        $locale = $o['locale'] ?? 'pt_BR';
        $forced = trim((string)($o['forced_title'] ?? ''));
        $forcedLine = $forced ? "Use este TÍTULO final (não gere outro): \"{$forced}\".\n" : "";

        return <<<PROMPT
{$forcedLine}
Escreva um GUIA/How-to com no minimo 600 palavras, em "{$locale}" com a frase chave de foco "{$kw}", passo a passo, escaneável, com checklists e dicas práticas.
insira a frase chave na primeira frase e se possivel em alguma conclusão no final
Corpo SEM <h1>. Meta_title/description curtas com a keyword. "image_alt" descritivo.
Saída: JSON conforme schema.
PROMPT;
    }
    
private static function faq(string $kw, array $o) : string {
    $locale = $o['locale'] ?? 'pt_BR';
    $forced = trim((string)($o['forced_title'] ?? ''));
    $forcedLine = $forced ? "Use este TÍTULO final (não gere outro): \"{$forced}\".\n" : "";

    return <<<PROMPT
{$forcedLine}
Escreva um conteúdo do tipo FAQ em "{$locale}" com foco em: "{$kw}".
Objetivo: responder rapidamente às dúvidas mais comuns do usuário, em linguagem natural e clara, com alta escaneabilidade.

REGRAS EDITORIAIS (não cite estas regras no texto):
- Tamanho mínimo: 600 palavras no total.
- A PRIMEIRA FRASE do texto deve conter a palavra-chave exatamente como "{$kw}".
- Estrutura SEM <h1>. Use apenas <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <br>, <hr>.
- Estrutura do corpo:
  1) Brevíssima introdução (2–3 frases) situando o tema e reforçando a intenção de responder dúvidas, contendo a palavra-chave.
  2) Entre 8 e 12 PERGUNTAS frequentes. Cada pergunta:
     - Deve vir em <h2> e ser curta, clara e objetiva (começar com "Como", "Qual", "Quando", "Por que", "Quanto", "Pode", etc.).
     - A resposta deve vir imediatamente após, em <p> (1–4 frases). Use <ul>/<ol> quando fizer sentido.
     - Evite redundâncias entre as perguntas; cubra ângulos práticos (passo a passo, prazos, custo, riscos, alternativas, manutenção, erros comuns, etc.).
  3) Conclusão curta com síntese prática e um CTA leve; TENTE inserir a palavra-chave novamente aqui.
- Não use percentuais soltos no texto (ex.: "30% de…") sem contexto claro.
- SEO:
  - Gere "meta_title" (≤ 60) e "meta_description" (≤ 155) persuasivos e naturais com a palavra-chave.
  - Gere "image_alt" descritivo contendo a palavra-chave de forma natural (sem superlativos vazios).
- Links:
  - Em "links.internal", sugira até 2 slugs internos coerentes.
  - Em "links.external", sugira até 2 URLs de referência confiáveis (site institucional, norma técnica, documento oficial).

SAÍDA OBRIGATÓRIA (apenas JSON UTF-8 válido, sem markdown, sem comentários):
{
  "title": "{$forced}",
  "titles_suggestions": [],
  "content": "<p>Introdução…</p><h2>Pergunta 1…</h2><p>Resposta…</p>…<h2>Pergunta N…</h2><p>Resposta…</p><p>Conclusão…</p>",
  "meta_title": "",
  "meta_description": "",
  "image_alt": "",
  "links": { "internal": [], "external": [] }
}

NÃO inclua <h1> em hipótese alguma. Não retorne nada além do JSON acima.
PROMPT;
}

}
