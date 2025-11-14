<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_PromptTitle {

    public static function build(string $template, string $keyword, array $opts = []) : string {
        $locale = $opts['locale'] ?? 'pt_BR';
        $min    = max(3, intval($opts['min'] ?? 4));
        $max    = max($min, intval($opts['max'] ?? 7));
        $style  = $opts['style'] ?? 'discover';

        $extra = self::extraRulesFor($template);

        return <<<PROMPT
Estamos em 2025 e Você é um redator sênior especializado em SEO e títulos de alto CTR em "{$locale}".

Gere entre {$min} e {$max} TÍTULOS criativos, fluídos e naturais para um conteúdo sobre:
"{$keyword}"


Estou criando um CONTEÚDO para com foco no google e quero dar algumas informações para elaborar um bom titulo, leia todas essas informações e depois de comporte-se como um jornalista sênior capaz de se eloquente escritor especialista com um impressionante vocabulário. Seu estilo de escrita é intrigante e consegue surpreender os leitores com opiniões bem elaboradas EM TITULO CURTOS.
Especificidade: títulos que fornecem detalhes, números ou nomes específicos tendem a capturar
atenção. Por exemplo, "15 melhores empresas" ou "Este homem de 32 anos estava ganhando US$ 17/hora".
Emoção e curiosidade: títulos que evocam uma resposta emocional ou despertam curiosidade podem envolver os leitores. 
Por exemplo, "As cidades mais tristes de todo o país" ou "Disney Ride Gets Revisão surpreendente".
Rentabilidade e relevância: tópicos que repercutem em um amplo público ou são oportuno/relevante pode ser um sucesso. Por exemplo, o foco em "trabalho híbrido" ou "ChatGPT".
Autoridade: quando o título parece confiável ou cita especialistas, ele pode atrair
trustandclicks.Ex.,"O que os especialistas dizem" ou "Pessoas emocionalmente inteligentes usam...".
Clareza: A clareza não deve ser sacrificada. deve dar uma ideia clara sobre o conteúdo do artigo.
Problema e Solução:Títulos que destacam um problema e fornecem uma solução podem ser
Envolvente. Por exemplo, "Fitbit responde a fãs furiosos com cinco correções de aplicativos muito necessárias".
E NÃO ESQUEÇA O ASPECTO NOTÍCIA
Oportunidade e relevância: plataformas como o Google Discover são feitas sob medida para fornecer conteúdo que é tendência atual ou é de relevância imediata para os usuários. Títulos que tocam em eventos atuais ou desenvolvimentos recentes têm maior probabilidade de serem revelados.
Engajamento do usuário: as pessoas são naturalmente atraídas pelas últimas notícias ou desenvolvimentos em tópicos nos quais estão interessados. Eles são mais propensos a clicar, ler e se envolver com artigos que fornecem novas percepções ou atualizações sobre eventos atuais.
Personalização: o Google Discover e plataformas semelhantes usam algoritmos que personalizam conteúdo baseado no comportamento do usuário. Se um usuário demonstrou interesse em um evento de notícias recente tópico, é mais provável que eles recebam conteúdo relacionado.
Urgência: as notícias vêm inerentemente com um senso de urgência.
mudanças, surpresas ou eventos impactantes podem fazer com que os usuários cliquem para saber mais. Por exemplo,
"Disney Ride recebe uma revisão surpreendente" ou "O criador do ChatGPT OpenAI isintalks...".

{$extra}

Para isso acontecer vou fornecer um palavra chave e você deve criar o titulo.
Essa é a palavra chave: {$keyword}.

Responda APENAS em JSON UTF-8 válido:
{ "titles": ["Título 1", "Título 2", "Título 3"] }
PROMPT;
    }

    private static function extraRulesFor(string $template) : string {
        switch ($template) {
            case 'discover_article':
                return "";
            case 'faq':
                return "- Prefira perguntas diretas e respostas curtas: “{$template} vale a pena?”, “como funciona…?”.";
            case 'review_roundup':
                return "- o foco deste titulo é para review, então Use comparativos claros: 'X vs Y', 'melhores opções de…', 'vale a pena em 2025?', mas se a frase chave conter 'melhor', entenda que é cabivel mudar para o plural, sempre focado nos fatores que mais tendem a fazer a pessoa clicar, mas sem clickbait";
            case 'review_single':
                return "- Foco em opinião real: 'vale a pena?', 'é bom mesmo?', 'review completo', não se esqueça da frase chave de foco.";
            case 'howto':
                return "- Comece com verbos de ação: 'como', 'aprenda a', 'faça', 'domine', 'descubra'.";
            case 'list':
                return "- Liste naturalmente: 'principais', 'melhores', 'dicas práticas', sem redundância.";
            case 'news':
                return "- Trate como notícia: mencione data/ano ou impacto ('em 2025', 'nova versão', 'lançamento').";
            default:
                return "- Crie títulos diretos, específicos e convidativos.";
        }
    }
}
