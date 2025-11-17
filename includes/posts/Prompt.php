<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Prompt
{

  /**
   * Constrói o prompt de CONTEÚDO conforme o template.
   * Assinatura compatível com o Generator:
   *   build(string $template, string $keyword, array $opts = [])
   */
  public static function build(string $template, string $keyword, string $url, array $opts = []): string
  {
    switch ($template) {
      case 'review_roundup':
        return self::review_roundup($keyword, $opts, $template);
      case 'review_single':
        return self::review_single($keyword, $opts, $url, $template);
      case 'news':
        return self::news($keyword, $opts);
      case 'howto':
        return self::howto($keyword, $opts);
      case 'faq':
        return self::faq($keyword, $opts);
      default:
        return self::article($keyword, $opts);
    }
  }

  private static function review_roundup(string $kw, array $o, string $template): string
  {
    $locale = $o['locale'] ?? 'pt_BR';
    $forced = trim((string)($o['forced_title'] ?? ''));
    $forcedLine = $forced
      ? 'Use este TÍTULO final (não gere outro): "' . $forced . "\".\n"
      : '';

    $prompt  = $forcedLine . "\n";
    $prompt .= 'Escreva um ' . $template . ' envolvente, com no minimo 2500 palavras, natural e humanizado em "' . $locale . '" sobre: "' . $kw . "\".\n\n";

    $prompt .= "Regras editoriais (não cite estas regras no texto):\n";
    $prompt .= "- Introdução: primeira frase DEVE conter a palavra-chave principal de forma fluida e não forçada.\n";
    $prompt .= "- Corpo em HTML SEM <h1> (use <h2>/<h3>/<p>/<ul>/<li>/<strong>/<em>...), linguagem fluida e prática.\n";
    $prompt .= "- Faça mini-comparações se aplicavel entre os produtos, com prós e contras realistas (sem verdade absoluta), ou seja, nunca diga que produto é melhor do que outro, a não ser em comparativos, então nesse caso, seria algo como \"produto x se destaca na bateria e produto y se destaca na distancia...\".\n";
    $prompt .= "- Repita a palavra-chave no primeiro H2 e naturalmente 1x por seção (variações semânticas ok).\n";
    $prompt .= "- Conclusão: destacando os principais pontos dos prós e crontas, como mencionados acima no quesito de comparação. insira tbm a frase chave.\n";
    $prompt .= "- Gere \"meta_title\" (≤ 60) e \"meta_description\" (≤ 155) com a keyword.\n";
    $prompt .= "- Gere \"image_alt\" descritivo com a keyword (sem superlativos artificiais).\n";
    $prompt .= "- Se possível, proponha 2 links internos (slugs) e 2 externos confiáveis em \"links\".\n\n";

    $prompt .= "Aja como um redator profissional e crie um artigo review magnético, dinâmico e persuasivo. O texto deve:\n\n";
    $prompt .= "Ter linguagem envolvente e convincente;\n\n";
    $prompt .= "Incluir chamadas para ação (CTA) incentivando o leitor a visitar a página oficial do produto;\n\n";
    $prompt .= "Deixe claro que o produto é vendido somente no site oficial;\n\n";
    $prompt .= "Fazer uma análise completa sobre o produto existente na frase chave.\n\n";
    $prompt .= "Incluir o links de afiliados no texto: sem link definido, então coloque links com #\n\n";
    $prompt .= "Ser otimizado com técnicas de SEO para ranquear bem no Google.\n\n";

    $prompt .= 'Palavra chave para rankear: ' . $kw . "\n";
    $prompt .= "Lembre-se: você está escrevendo para pessoas do nível iniciante ao avançado, então mantenha um tom conversacional e um estilo de explicação excepcional, use palavra de transição do inicio ao fim do texto\n\n";

    $prompt .= "Saída OBRIGATÓRIA: JSON UTF-8 **válido** no formato:\n";
    $prompt .= "{\n";
    $prompt .= '  "title": "' . $forced . "\",\n";
    $prompt .= '  "titles_suggestions": [],' . "\n";
    $prompt .= '  "content": "<h2>...</h2>...",' . "\n";
    $prompt .= '  "meta_title":"", "meta_description":"", "image_alt":"",' . "\n";
    $prompt .= '  "links":{"internal":[],"external":[]}' . "\n";
    $prompt .= "}\n";
    $prompt .= "NÃO use markdown. NÃO inclua <h1> no content. NÃO use percentuais no corpo do texto.\n";

    return $prompt;
  }

  private static function review_single(string $kw, array $o, string $url, string $template): string
  {
    $locale = $o['locale'] ?? 'pt_BR';
    $forced = trim((string)($o['forced_title'] ?? ''));
    $forcedLine = $forced ? "Use este TÍTULO final (não gere outro): \"{$forced}\".\n" : "";

    $forcedLine = $forced
      ? 'Use este TÍTULO final (não gere outro): "' . $forced . "\".\n"
      : '';

    $prompt  = $forcedLine . "\n";
    $prompt .= 'Escreva um ' . $template . ' envolvente com no minimo 1500 palavras, natural e humanizado em "' . $locale . '" sobre: "' . $kw . "\".\n\n";

    $prompt .= "Regras editoriais (não cite estas regras no texto):\n";
    $prompt .= "- Introdução: primeira frase DEVE conter a palavra-chave principal de forma fluida e não forçada.\n";
    $prompt .= "- Corpo em HTML SEM <h1> (use <h2>/<h3>/<p>/<ul>/<li>/<strong>/<em>...), linguagem fluida e prática.\n";
    $prompt .= "- Estruture em seções se aplicavel (sem percentuais no texto):\n";
    $prompt .= "  • Precisão do rastreamento e alcance\n";
    $prompt .= "  • Bateria e recarga\n";
    $prompt .= "  • Conforto e tamanho\n";
    $prompt .= "  • Recursos extras\n";
    $prompt .= "  • Facilidade de uso\n";
    $prompt .= "- Faça mini-comparações se aplicavel entre os produtos (“os melhores”), com prós e contras realistas (sem verdade absoluta).\n";
    $prompt .= "- Repita a palavra-chave no primeiro H2 e naturalmente 1x por seção (variações semânticas ok).\n";
    $prompt .= "- Conclusão: “vale a pena?”, para quem é, e um CTA leve.\n";
    $prompt .= "- Gere \"meta_title\" (≤ 60) e \"meta_description\" (≤ 155) com a keyword.\n";
    $prompt .= "- Gere \"image_alt\" descritivo com a keyword (sem superlativos artificiais).\n";
    $prompt .= "- Se possível, proponha 2 links internos (slugs) e 2 externos confiáveis em \"links\".\n\n";

    $prompt .= "Aja como um redator profissional e crie um artigo review magnético, dinâmico e persuasivo. O texto deve:\n\n";
    $prompt .= "Ter linguagem envolvente e convincente;\n\n";
    $prompt .= "Incluir chamadas para ação (CTA) incentivando o leitor a visitar a página oficial do produto;\n\n";
    $prompt .= "Deixe claro que o produto é vendido somente no site oficial;\n\n";
    $prompt .= 'Fazer uma análise completa sobre o produto ou ' . $url . ", então acesse o site e tire as informações\n\n";
    $prompt .= "Incluir o link de afiliado no texto: sem link definido, então coloque link com #\n\n";
    $prompt .= "Ser otimizado com técnicas de SEO para ranquear bem no Google.\n\n";

    $prompt .= 'Palavra chave para rankear: ' . $kw . "\n";
    $prompt .= "Lembre-se: você está escrevendo para pessoas do nível iniciante ao avançado, então mantenha um tom conversacional e um estilo de explicação excepcional, use palavra de transição do inicio ao fim do texto\n\n";

    $prompt .= "Saída OBRIGATÓRIA: JSON UTF-8 **válido** no formato:\n";
    $prompt .= "{\n";
    $prompt .= '  "title": "' . $forced . "\",\n";
    $prompt .= '  "titles_suggestions": [],' . "\n";
    $prompt .= '  "content": "<h2>...</h2>...",' . "\n";
    $prompt .= '  "meta_title":"", "meta_description":"", "image_alt":"",' . "\n";
    $prompt .= '  "links":{"internal":[],"external":[]}' . "\n";
    $prompt .= "}\n";
    $prompt .= "NÃO use markdown. NÃO inclua <h1> no content. NÃO use percentuais no corpo do texto.\n";

    return $prompt;
  }

  private static function article(string $kw, array $o): string
  {
    $locale = $o['locale'] ?? 'pt_BR';
    $forced = trim((string)($o['forced_title'] ?? ''));
    $forcedLine = $forced ? "Use este TÍTULO final (não gere outro): \"{$forced}\".\n" : "";

    $forcedLine = $forced
      ? 'Use este TÍTULO final (não gere outro): "' . $forced . "\".\n"
      : '';

    $prompt  = $forcedLine . "\n";
    $prompt .= 'Escreva um ARTIGO com no minimo 1500 palavras, otimizado para Discover em "' . $locale . '" sobre: "' . $kw . "\".\n\n";

    $prompt .= "Atue como um especialista em SEO e um profundo conhecedor do título. Meu objetivo é criar um conteúdo superior ao dos meus concorrentes. O artigo deve ter excelente usabilidade e seguir rigorosamente as diretrizes do Google para alcançar a primeira posição nos resultados de busca. Vou enviar três artigos para análise. Por favor, aguarde enquanto envio cada um. Seu papel será identificar os pontos fortes de cada artigo e aguardar novas instruções antes de prosseguir. Apenas me deu um ok que guardou a informação desse texto, não crie nada agora!\n";
    $prompt .= "Palavras primarias:\n";
    $prompt .= "Palavras secundárias:\n";
    $prompt .= "OBJETIVO DO PROMPT\n";
    $prompt .= "• Criar um artigo que supere os concorrentes, com base nas diretrizes de SEO, GEO (Generative Engine Optimization) e AEO (Answer Engine Optimization).\n";
    $prompt .= "• Gerar um conteúdo humanizado, escaneável, semântico, original e otimizado para ranqueamento no Google e destaque em IAs generativas.\n\n";

    $prompt .= "🔧 INSTRUÇÕES DETALHADAS PARA GERAÇÃO DO CONTEÚDO\n";
    $prompt .= "🔁 Densidade e Repetição da Palavra-chave Primária\n";
    $prompt .= "• Estime a quantidade total de palavras do artigo.\n";
    $prompt .= "• Divida por 125 para obter a meta de repetições.\n";
    $prompt .= "• Distribua essas repetições de forma natural entre os blocos H2, garantindo presença no primeiro parágrafo de cada H2.\n";

    $prompt .= "🧩 Estrutura de Escrita\n";
    $prompt .= "H1 — Introdução Principal\n";
    $prompt .= "• Palavra-chave primária obrigatoriamente na primeira linha.\n";
    $prompt .= "• Parágrafos com 2 a 5 linhas cada.\n";
    $prompt .= "• Texto claro, natural, fluido e convidativo.\n";

    $prompt .= "H2 — Blocos Principais\n";
    $prompt .= "• No mínimo 2 parágrafos por bloco.\n";
    $prompt .= "• Palavra-chave primária aparece no primeiro parágrafo sempre que possível.\n";
    $prompt .= "• Conteúdo profundo, escaneável e informativo.\n";

    $prompt .= "H3 — Subtópicos e Perguntas (AEO)\n";
    $prompt .= "• 1 ou 2 parágrafos de 2 a 4 linhas cada.\n";
    $prompt .= "• Tom de resposta objetiva e clara, ideal para snippets e IAs.\n";

    $prompt .= "🧠 SEO SEMÂNTICO\n";
    $prompt .= "Regras para Palavras-chave Secundárias\n";
    $prompt .= "• Cada termo deve aparecer pelo menos 2 vezes, distribuído em blocos diferentes.\n";
    $prompt .= "• Uso natural e contextualizado, com flexões e variações aceitas.\n";

    $prompt .= "Palavras Semânticas\n";
    $prompt .= "• Extraia termos relevantes dos concorrentes (sem copiar frases).\n";
    $prompt .= "• Aplique ao longo do conteúdo com naturalidade e valor informativo.\n";

    $prompt .= "Palavras LLM (Inteligência Semântica)\n";
    $prompt .= "• Produza variações naturais, evitando repetições mecânicas.\n";
    $prompt .= "• Adapte o tom para parecer uma resposta natural de IA (como ChatGPT, Gemini, Bard).\n";

    $prompt .= "🎤 Tom de Voz e Estilo\n";
    $prompt .= "• Linguagem informacional, fluida, humanizada e escaneável.\n";
    $prompt .= "• Adote estilo didático, acolhedor e direto, com frases claras e bem conectadas.\n";
    $prompt .= "• Em conteúdos de review, escreva como um usuário que testou, usando provas sociais e copy persuasiva.\n";

    $prompt .= "✍️ Boas Práticas de Escrita\n";
    $prompt .= "• Evite repetições mecânicas, jargões excessivos ou linguagem robótica.\n";
    $prompt .= "• Use bullet points com moderação (máx. 2 vezes por artigo) para organizar listas, instruções ou comparações.\n";
    $prompt .= "• Conclusão obrigatória: 1 a 2 parágrafos de até 4 linhas, com CTA.\n";
    $prompt .= "• Último H2 obrigatório: “Dúvidas Frequentes” com respostas curtas em bullet (250–300 caracteres por item).\n";

    $prompt .= "🔒 Originalidade e Plágio Zero\n";
    $prompt .= "• Nenhum trecho dos concorrentes pode ser copiado.\n";
    $prompt .= "• Todo o texto deve ser original, fluido e parecer escrito por humano com domínio do tema.\n";

    $prompt .= "🧠 Modelagem Estrutural\n";
    $prompt .= "• Analise os concorrentes e modele a estrutura (não o conteúdo).\n";
    $prompt .= "• Aprofunde tópicos superficiais, organize melhor e adicione exemplos únicos.\n";

    $prompt .= "🧠 Ao Final, Gere Também:\n";
    $prompt .= "🔹 Meta Tags\n";
    $prompt .= "• Meta Title: até 60 caracteres, com a palavra-chave primária.\n";
    $prompt .= "• Meta Description: até 155 caracteres, com CTA e palavra-chave primária.\n";

    $prompt .= "🖼️ Imagem Realista para SEO Visual\n";
    $prompt .= "• Pessoa única, foco no rosto, fundo neutro e desfocado.\n";
    $prompt .= "• Estilo: hiper-realista, cinematográfico.\n";
    $prompt .= "• Proporção: 16:9 – resolução alta.\n";
    $prompt .= "• ALT da imagem deve conter a palavra-chave primária exata.\n\n";

    $prompt .= "Saída: JSON UTF-8 (title, titles_suggestions[], content, meta_title, meta_description, image_alt, links{internal[],external[]}).\n";
    $prompt .= "Sem markdown, sem <h1>.\n";

    return $prompt;
  }

  private static function news(string $kw, array $o): string
  {
    $locale = $o['locale'] ?? 'pt_BR';
    $forced = trim((string)($o['forced_title'] ?? ''));
    $forcedLine = $forced ? "Use este TÍTULO final (não gere outro): \"{$forced}\".\n" : "";

    $forcedLine = $forced
      ? 'Use este TÍTULO final (não gere outro): "' . $forced . "\".\n"
      : '';

    $prompt  = $forcedLine . "\n";
    $prompt .= 'Escreva uma NOTÍCIA factual em "' . $locale . '" sobre: "' . $kw . '", com lide claro.' . "\n\n";
    $prompt .= "Corpo em <h2>/<h3> SEM <h1>. Contexto, quem/onde/quando, impacto e próximos passos.\n";
    $prompt .= "Meta_title/description curtas com a keyword.\n";
    $prompt .= "Saída: JSON conforme schema.\n";

    return $prompt;
  }

  private static function howto(string $kw, array $o): string
  {
    $locale = $o['locale'] ?? 'pt_BR';
    $forced = trim((string)($o['forced_title'] ?? ''));
    $forcedLine = $forced ? "Use este TÍTULO final (não gere outro): \"{$forced}\".\n" : "";

    $forcedLine = $forced
      ? 'Use este TÍTULO final (não gere outro): "' . $forced . "\".\n"
      : '';

    $prompt  = $forcedLine . "\n";
    $prompt .= 'Escreva um GUIA/How-to com no minimo 600 palavras, em "' . $locale . '" com a frase chave de foco "' . $kw . '", passo a passo, escaneável, com checklists e dicas práticas.' . "\n";
    $prompt .= "insira a frase chave na primeira frase e se possivel em alguma conclusão no final\n";
    $prompt .= "Corpo SEM <h1>. Meta_title/description curtas com a keyword. \"image_alt\" descritivo.\n";
    $prompt .= "Saída: JSON conforme schema.\n";

    return $prompt;
  }

  private static function faq(string $kw, array $o): string
  {
    $locale = $o['locale'] ?? 'pt_BR';
    $forced = trim((string)($o['forced_title'] ?? ''));
    $forcedLine = $forced ? "Use este TÍTULO final (não gere outro): \"{$forced}\".\n" : "";

    $forcedLine = $forced
      ? 'Use este TÍTULO final (não gere outro): "' . $forced . "\".\n"
      : '';

    $prompt  = $forcedLine . "\n";
    $prompt .= 'Escreva um conteúdo do tipo FAQ em "' . $locale . '" com foco em: "' . $kw . "\".\n";
    $prompt .= "Objetivo: responder rapidamente às dúvidas mais comuns do usuário, em linguagem natural e clara, com alta escaneabilidade.\n\n";

    $prompt .= "REGRAS EDITORIAIS (não cite estas regras no texto):\n";
    $prompt .= "- Tamanho mínimo: 600 palavras no total.\n";
    $prompt .= '- A PRIMEIRA FRASE do texto deve conter a palavra-chave exatamente como "' . $kw . "\".\n";
    $prompt .= "- Estrutura SEM <h1>. Use apenas <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <br>, <hr>.\n";
    $prompt .= "- Estrutura do corpo:\n";
    $prompt .= "  1) Brevíssima introdução (2–3 frases) situando o tema e reforçando a intenção de responder dúvidas, contendo a palavra-chave.\n";
    $prompt .= "  2) Entre 8 e 12 PERGUNTAS frequentes. Cada pergunta:\n";
    $prompt .= '     - Deve vir em <h2> e ser curta, clara e objetiva (começar com "Como", "Qual", "Quando", "Por que", "Quanto", "Pode", etc.).' . "\n";
    $prompt .= "     - A resposta deve vir imediatamente após, em <p> (1–4 frases). Use <ul>/<ol> quando fizer sentido.\n";
    $prompt .= "     - Evite redundâncias entre as perguntas; cubra ângulos práticos (passo a passo, prazos, custo, riscos, alternativas, manutenção, erros comuns, etc.).\n";
    $prompt .= "  3) Conclusão curta com síntese prática e um CTA leve; TENTE inserir a palavra-chave novamente aqui.\n";
    $prompt .= "- Não use percentuais soltos no texto (ex.: \"30% de…\") sem contexto claro.\n";
    $prompt .= "- SEO:\n";
    $prompt .= "  - Gere \"meta_title\" (≤ 60) e \"meta_description\" (≤ 155) persuasivos e naturais com a palavra-chave.\n";
    $prompt .= '  - Gere "image_alt" descritivo contendo a palavra-chave de forma natural (sem superlativos vazios).' . "\n";
    $prompt .= "- Links:\n";
    $prompt .= '  - Em "links.internal", sugira até 2 slugs internos coerentes.' . "\n";
    $prompt .= '  - Em "links.external", sugira até 2 URLs de referência confiáveis (site institucional, norma técnica, documento oficial).' . "\n\n";

    $prompt .= "SAÍDA OBRIGATÓRIA (apenas JSON UTF-8 válido, sem markdown, sem comentários):\n";
    $prompt .= "{\n";
    $prompt .= '  "title": "' . $forced . "\",\n";
    $prompt .= '  "titles_suggestions": [],' . "\n";
    $prompt .= '  "content": "<p>Introdução…</p><h2>Pergunta 1…</h2><p>Resposta…</p>…<h2>Pergunta N…</h2><p>Resposta…</p><p>Conclusão…</p>",' . "\n";
    $prompt .= '  "meta_title": "",' . "\n";
    $prompt .= '  "meta_description": "",' . "\n";
    $prompt .= '  "image_alt": "",' . "\n";
    $prompt .= '  "links": { "internal": [], "external": [] }' . "\n";
    $prompt .= "}\n\n";
    $prompt .= "NÃO inclua <h1> em hipótese alguma. Não retorne nada além do JSON acima.\n";

    return $prompt;
  }
}
