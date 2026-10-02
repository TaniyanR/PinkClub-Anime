# PinkClub-Anime 改修・検証記録（2026-10-02）

比較対象: Anime `378a19c`、PinkClub-FL `90a5a99`。
参考: Libraryの `PinkClub開発ルール/02_PinkClub修正用.md`。

## 実装

| 対象 | 結果 |
| --- | --- |
| 共通公開UI | FLの32件グリッド、画像寸法・読み込み優先度、サンプル画像モーダル、ランキング表示、スマホのRSS省略を反映 |
| Anime固有 | FANZA / digital / anime、ジャンル・メーカー・レーベル・シリーズと声優情報を維持。FL固有の作者表示・シリーズ転送は適用しない |
| API | 未キャッシュ通信をAPI ID単位のファイルロックで直列化し、開始間隔を1秒以上に制限。分類用の数値フロアIDはFloorListのAnimeコードから解決 |
| 検索 | 商品の関連テーブルを使った検索、分類の完全一致、RSS混入判定の一括化。関連テーブルの複合索引を追加 |
| 計測 | キャッシュHTML内のトークンを配信時に訪問者へ署名。閲覧ビーコンは署名・閲覧時間・同一生成元・商品IDを検証。日単位のIPハッシュで重複排除 |
| 掲載終了 | 一覧・検索・RSS・サイトマップ・サンプル画像・OGP画像の公開対象を揃える。終了した商品とサンプル画像は410、未公開・存在しない商品は404 |
| 初期化 | 管理画面を開く前に固定ページを作成。旧025マイグレーションは固定ページ未作成の新規DBでも停止しない。既存管理者・商品を保持。未設定の計測署名用秘密値はランダムに生成し、既存の秘密値は保持 |
| セキュリティ | RSS取得の公開IP検査・DNS固定・リダイレクト/サイズ制限、相互リンク遷移先照合、秘密ファイル・コード・ログ・キャッシュのHTTP公開禁止 |
| HTML | 動画iframeのtitle、非表示モーダルのhidden、画像の空src、フォームtypeを修正。メニュー変数が詳細の商品データを上書きする不具合を解消 |
| 運用URL | config.local.phpのsite.base_urlと環境変数BASE_URLを利用。CLIでファイルシステムのパスが公開URLに追加される問題を解消 |

## 実測した検証

PHP 8.3 / MariaDB 10.11 / Apache 2.4で、隔離した `anime_audit` DBと架空の商品40件、ローカルの模擬APIを使用。本番の商品・認証情報は使用していない。

- 新規セットアップ7工程、再実行の成功、ランダム初期パスワード、管理者パスワードと商品件数の維持。
- 商品同期40件、別リクエストの待機、同一リクエストのAPIキャッシュ。
- 公開17画面の200応答、成人向けratingとOGPの重複防止、セキュリティヘッダー。
- sitemap / sitemap_index / feedのXML解析、robotsの公開URL、サンプル画像のJSON。
- HTMLキャッシュHITで異なる2つのIPに異なる署名を配信し、アクセスと商品閲覧を2件計測。
- 商品の終了時410、検索・feed・sitemapからの除外、復元時200、存在しない商品と未来の発売日は404。
- Apacheでトップと商品を200、旧public商品URLを301、config.local / lib / scripts / .git / storageを403。CSSはimmutableキャッシュ。サブディレクトリの旧public商品URLとindex.phpも正しい公開URLへ301。
- PHPセッションとCSRFを使用した管理ログイン、管理画面7ページの200応答。最終検証のPHP Warning/Fatal/Noticeなし。
- HTML5検証ツールで、取得した17ページに構造・属性・アクセシビリティ関連のエラーなし。既存のインラインstyle、小文字DOCTYPE、void要素の閉じ方、末尾空白はスタイル規約の指摘として残る。W3C公式サービスでの認証を意味しない。
- 取得HTMLのinline script 144個のJavaScript構文検査。
- DB不要の回帰テスト `php tests/regression.php`。危険なRSS URL、クエリ違いの重複排除、キャッシュ署名、CLI URL、Animeフロア解決を確認。
- PHPファイルの構文検査、`git diff --check`。

## 未検証の範囲

Chromeの実行ファイルはこの環境で起動時にSIGSEGVとなったため、PC/SPの画面描画・操作・実行時JavaScript例外は未確認。モーダルの実操作や画面幅の検証を済ませたとは扱わない。

実FANZA APIの接続、配信画像・動画の再生、本番ホスティング、メール送信、cronの常駐運用、Google/Bingの検索登録・インデックス状況、IndexNowの本番送信、実ユーザーのCore Web Vitalsは未確認。速度の改善策を実装したが、スコアや順位の向上を保証するものではない。

## 公開手順

1. ファイルとDBをバックアップし、更新を配置する。Apacheではmod_rewriteとAllowOverrideを有効にする。nginxでは.htaccessが適用されないため、秘密ディレクトリ・設定ファイルのdeny、公開URLへのrewrite、HTTPS、静的ファイルキャッシュを同等に設定する。
2. `config.local.php` の `site.base_url` に正しいHTTPS公開URLを指定する。例: `['site' => ['base_url' => 'https://example.com']]`。既存のDB設定は保持する。cronでもこのURL設定を共有する。
3. 管理者としてセットアップを実行し、新しい027の検索索引を適用する。初期パスワードは初回のみ表示される。既存管理者がadmin/passwordのままなら個人設定で変更する。
4. config.local.php、PHPセッション・ログ・storageの書き込み権限を確認し、管理ログイン、分類一覧、検索、詳細、サンプル画像・動画をPCとSPで操作する。
5. 管理画面でAPI IDとアフィリエイトIDを設定し「10件テスト取得」。FANZA/digital/animeの取得先を確認し、cronへauto_import.phpを登録する。
6. HTTPSの正規URL、301、404、410、robots、sitemap、feed、OGP、秘密ファイル403を本番ホストでも確認する。サンプル画像がない商品・動画がない商品も確認する。
7. Google Search ConsoleとBing Webmaster Toolsの所有権確認とサイトマップ送信を実施する。IndexNowは正しい公開ホスト・キー・キー確認URLを設定してからキュー送信を確認する。検索結果の反映には外部サービスの処理が必要。
8. Chromeのモバイル表示と実機、Lighthouse/PageSpeed InsightsでLCP・CLS・INPを測定する。外部広告・画像の応答速度も含めて確認する。

## 照合した一次資料

- Google 成人向けコンテンツ: https://developers.google.com/search/docs/specialty/explicit/guidelines
- Google robots.txt: https://developers.google.com/search/docs/crawling-indexing/robots/intro
- Bing Webmaster Guidelines: https://www.bing.com/webmasters/help/webmaster-guidelines-30fba23a
- IndexNow: https://www.indexnow.org/documentation
- LCP改善: https://web.dev/articles/optimize-lcp
- FANZA Affiliate API: https://affiliate.dmm.com/api/

FANZA APIの一部公式ページは地域制限で本文を取得できないため、実APIに関する最終確認は管理画面の接続テストで行う。
