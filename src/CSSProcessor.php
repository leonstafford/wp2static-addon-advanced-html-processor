<?php

// TODO: rewrite whole class: no baseUrl, no placeholder URLs, etc..
namespace WP2Static;

class CSSProcessor {

    public $placeholder_url;
    public $raw_css;
    public $page_url;
    public $css_doc;
    private $settings;

    public function __construct() {
        $plugin = Controller::getInstance();
        $this->settings = $plugin->options->getSettings( true );
    }

    public function processCSS(
        string $css_document,
        string $page_url
    ) : string {
        $protocol = $this->getTargetSiteProtocol( $this->settings['baseUrl'] );

        $this->placeholder_url = $protocol . 'PLACEHOLDER.wpsho/';

        $this->raw_css = $css_document;
        // initial rewrite of all site URLs to placeholder URLs
        $this->rewriteSiteURLsToPlaceholder();

        $css_parser = new \Sabberworm\CSS\Parser( $this->raw_css );
        $this->css_doc = $css_parser->parse();

        $this->page_url = new \Net_URL2( $page_url );

        foreach ( $this->css_doc->getAllValues() as $node_value ) {
            if ( $node_value instanceof \Sabberworm\CSS\Value\URL ) {
                $original_link = $node_value->getURL();

                $original_link = trim( trim( $original_link, "'" ), '"' );

                $inline_img =
                    strpos( $original_link, 'data:image' );

                if ( $inline_img !== false ) {
                    continue;
                }

                if ( $this->isInternalLink( $original_link ) ) {
                    if ( ! isset( $this->settings['rewriteRules'] ) ) {
                        $this->settings['rewriteRules'] = '';
                    }

                    // add base URL to rewriteRules
                    $this->settings['rewriteRules'] .=
                        PHP_EOL .
                            $this->placeholder_url . ',' .
                            $this->settings['baseUrl'];

                    $rewrite_from = array();
                    $rewrite_to = array();

                    $rewrite_rules = explode(
                        "\n",
                        str_replace(
                            "\r",
                            '',
                            $this->settings['rewriteRules']
                        )
                    );

                    $tmp_rules = array();

                    foreach ( $rewrite_rules as $rewrite_rule_line ) {
                        if ( $rewrite_rule_line ) {
                            list($from, $to) =
                                explode( ',', $rewrite_rule_line );

                            $tmp_rules[ $from ] = $to;
                        }
                    }

                    uksort(
                        $tmp_rules,
                        function ( $str1, $str2 ) {
                            return 0 - strcmp( $str1, $str2 );
                        }
                    );

                    foreach ( $tmp_rules as $from => $to ) {
                        $rewrite_from[] = $from;
                        $rewrite_to[] = $to;
                    }

                    $rewritten_url = str_replace(
                        $rewrite_from,
                        $rewrite_to,
                        $original_link
                    );

                    $rewritten_url = new \Sabberworm\CSS\Value\CSSString(
                        $rewritten_url
                    );

                    $node_value->setURL( $rewritten_url );
                }
            }
        }

        return $this->css_doc->render();
    }

    public function isInternalLink(
        string $link,
        string $domain = ''
    ) : bool {
        if ( ! $domain ) {
            $domain = $this->placeholder_url;
        }

        $is_internal_link = parse_url( $link, PHP_URL_HOST ) === parse_url(
            $domain,
            PHP_URL_HOST
        );

        return $is_internal_link;
    }

    /**
     * Rewrite site urls to placeholder NOTE: unused
     *
     * @throws WP2StaticException
     */
    public function rewriteSiteURLsToPlaceholder() : void {
        $site_url = SiteInfo::getUrl( 'site' );

        if ( ! is_string( $site_url ) ) {
            $err = 'Site URL not defined ';
            WsLog::l( $err );
            throw new WP2StaticException( $err );
        }

        $rewritten_source = str_replace(
            array(
                $site_url,
                addcslashes( $site_url, '/' ),
            ),
            array(
                $this->placeholder_url,
                addcslashes( $this->placeholder_url, '/' ),
            ),
            $this->raw_css
        );

        $this->raw_css = $rewritten_source;
    }

    public function isValidURL( string $url ) : bool {
        // NOTE: not using native URL filter as it won't accept
        // non-ASCII URLs, which we want to support
        $url = trim( $url );

        if ( $url == '' ) {
            return false;
        }

        if ( strpos( $url, '.php' ) !== false ) {
            return false;
        }

        if ( strpos( $url, ' ' ) !== false ) {
            return false;
        }

        if ( $url[0] == '#' ) {
            return false;
        }

        return true;
    }

    public function getTargetSiteProtocol( string $url ) : string {
        $protocol = '//';

        if ( strpos( $url, 'https://' ) !== false ) {
            $protocol = 'https://';
        } elseif ( strpos( $url, 'http://' ) !== false ) {
            $protocol = 'http://';
        } else {
            $protocol = '//';
        }

        return $protocol;
    }
}

