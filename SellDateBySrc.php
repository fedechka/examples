<?php

/**
 * Отчет "Продажа / Дата по участникам"
 *
 * NOTE: Работает с мастер-DB, потому что использует временные таблицы
 */

namespace Da\Stats\Admin;

class SellDateBySrc extends BaseAdmin
{
    /**
     * Если выставлено, выбрана группировка по паблишерам, а не по сайтам
     *
     * @var boolean
     */
    protected $group_by_publisher = false;
    protected $tables_for_getmonths = array('stats.day_sell');


    /**
     * Если выставлено, значит в выборку включен сегодняшний день,
     * и значит дельты за сегодня по сравнению со вчера надо считать по полным завершенным часам.
     *
     * @var boolean
     */
    protected $is_today_included = false;


    public $date_start  = '';
    public $date_end    = '';
    public $days_count  = 0;
    public $date_start_prev = '';
    public $date_end_prev = '';


    /**
     * Признаки того, что таблицы статистики и отчета уже наполнены
     * Используется потому, что методы наполнения таблиц вызываются
     * в нескольких разных методах - report, getCount, getTotal
     *
     * @var boolean
     */
    private $tmp_stats_filled = false;
    private $tmp_report_filled = false;

    /**
     * Содержит фильтр временных таблиц, определяется в конструкторе.
     *
     * @var FilterTemp
     */
    private $filter;


    public function __construct(\Da\Admin\Admin $admin, $period, $group_by_publisher)
    {
        parent::__construct($admin);

        $this->filter = new FilterTemp('tmp_sites_users');

        // Выбор группировки по паблишеру
        $this->group_by_publisher = $group_by_publisher;

        // Устанавливаем период выборки
        list($this->is_today_included, $this->date_start, $this->date_end,
                $this->days_count, $this->date_start_prev, $this->date_end_prev) =
                \Da\Helper\Dates::getPeriodsForStats($period);

        // Создаем временную таблицу для фильтрации по сайтам/юзерам,
        // и сразу заполняем ее всеми сайтами, по которым есть статистика.
        // Фильтрацию сайтов-юзеров будем производить по мере установки фильтров.
        $this->createTempTableSites();
        $this->fillTempTableSites();

        // Принудительно прогоняем фильтрацию по менеджерам.
        // Это нужно из-за того, что в конструкторе родителя может выставиться принудительная фильтрация
        // для сейлзов.
        $this->filter->byManagers($this->managers);

        // Создаем временную таблицу для отчета
        $this->createTempTableReport();

        // Создаем временные таблицы для сырых статистик за расчетный период и предыдущий
        $this->createTempTableStats();
    }


    /**
     * Создает временную таблицу сайтов/юзеров, куда будут отбираться только те,
     * кто подходит под фильтры
     *
     */
    private function createTempTableSites()
    {
        global $db;

        // Создаем табличку со всеми полями, которые могут участвовать в фильтрации
        $query = "
            CREATE TEMP TABLE tmp_sites_users(
                site_id integer NOT NULL,
                site_name character varying(255),
                site_url character varying(255),
                country_id integer,
                profit_type character varying(20),
                min_teaser_rating integer,

                user_id integer,
                email character varying(255),
                subsystem_id integer,
                is_exchanger boolean,
                is_disabled boolean,

                source character varying(30) NOT NULL DEFAULT '',
                medium character varying(30) NOT NULL DEFAULT '',
                campaign character varying(30) NOT NULL DEFAULT '',
                ad character varying(30) NOT NULL DEFAULT '',

                CONSTRAINT pk_tmp_sites_users PRIMARY KEY (site_id)
            )
        ";
        $sth = $db->prepare($query);
        $sth->execute();
    }


    /**
     * Создает временную таблицу для отчета и сразу инитит ее айдишниками
     *
     */
    private function createTempTableReport()
    {
        global $db;

        // id - site_id или user_id, в зависимости от группировки отчета, по сайтам или по паблишерам.
        // NOTE: Поля с суффиксом _for_delta предназначены для расчета дельт,
        // если в выборку включен сегодняшний день - в этом случае дельты
        $query = "
            CREATE TEMP TABLE tmp_report(
                id integer NOT NULL,
                name character varying(255),

                profit numeric(16,4) NOT NULL DEFAULT 0,
                delta_profit numeric(16,4) NOT NULL DEFAULT 0,

                system_profit numeric(16,4) NOT NULL DEFAULT 0,
                delta_system_profit numeric(16,4) NOT NULL DEFAULT 0,

                percent_system_profit numeric(16,4) NOT NULL DEFAULT 0,
                delta_percent_system_profit numeric(16,4) NOT NULL DEFAULT 0,

                src_shows bigint NOT NULL DEFAULT 0,
                delta_src_shows bigint NOT NULL DEFAULT 0,
                src_clicks bigint NOT NULL DEFAULT 0,
                delta_src_clicks bigint NOT NULL DEFAULT 0,
                src_ctr numeric(16,4) NOT NULL DEFAULT 0,
                delta_src_ctr numeric(16,4) NOT NULL DEFAULT 0,
                src_click_price numeric(16,4) NOT NULL DEFAULT 0,
                delta_src_click_price numeric(16,4) NOT NULL DEFAULT 0,
                src_cpm numeric(16,4) NOT NULL DEFAULT 0,
                delta_src_cpm numeric(16,4) NOT NULL DEFAULT 0,

                buy_shows bigint NOT NULL DEFAULT 0,
                delta_buy_shows bigint NOT NULL DEFAULT 0,
                buy_clicks bigint NOT NULL DEFAULT 0,
                delta_buy_clicks bigint NOT NULL DEFAULT 0,
                buy_ctr numeric(16,4) NOT NULL DEFAULT 0,
                delta_buy_ctr numeric(16,4) NOT NULL DEFAULT 0,
                buy_click_price numeric(16,4) NOT NULL DEFAULT 0,
                delta_buy_click_price numeric(16,4) NOT NULL DEFAULT 0,
                buy_cpm numeric(16,4) NOT NULL DEFAULT 0,
                delta_buy_cpm numeric(16,4) NOT NULL DEFAULT 0,

                block_shows bigint NOT NULL DEFAULT 0,
                delta_block_shows bigint NOT NULL DEFAULT 0,
                block_ctr numeric(16,4) NOT NULL DEFAULT 0,
                delta_block_ctr numeric(16,4) NOT NULL DEFAULT 0,
                block_cpm numeric(16,4) NOT NULL DEFAULT 0,
                delta_block_cpm numeric(16,4) NOT NULL DEFAULT 0,
                block_shows_empty bigint NOT NULL DEFAULT 0,
                effective_block_shows_pc numeric(16,4) NOT NULL DEFAULT 0,

                profit_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                system_profit_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                percent_system_profit_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                src_shows_for_delta bigint NOT NULL DEFAULT 0,
                src_clicks_for_delta bigint NOT NULL DEFAULT 0,
                src_ctr_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                src_click_price_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                src_cpm_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                buy_shows_for_delta bigint NOT NULL DEFAULT 0,
                buy_clicks_for_delta bigint NOT NULL DEFAULT 0,
                buy_ctr_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                buy_click_price_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                buy_cpm_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                block_shows_for_delta bigint NOT NULL DEFAULT 0,
                block_ctr_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                block_cpm_for_delta numeric(16,4) NOT NULL DEFAULT 0,

                CONSTRAINT pk_tmp_report PRIMARY KEY (id)
            )
        ";

        $sth = $db->prepare($query);
        $sth->execute();
    }


    /**
     * Создает таблицу для статистики за расчетный и предыдущий периоды
     *
     */
    private function createTempTableStats()
    {
        global $db;

        // NOTE: Поля с суффиксом _for_delta предназначены для расчета дельт,
        // если в выборку включен сегодняшний день - в этом случае дельты
        // за сегодня по сравнению со вчера надо считать по полным завершенным часам.
        foreach (array('tmp_stats', 'tmp_stats_prev') as $table) {
            $query = "
                CREATE TEMP TABLE " . $table . "(
                    site_id integer NOT NULL,

                    src_shows bigint NOT NULL DEFAULT 0,
                    src_clicks bigint NOT NULL DEFAULT 0,
                    buy_shows bigint NOT NULL DEFAULT 0,
                    buy_clicks bigint NOT NULL DEFAULT 0,
                    block_shows bigint NOT NULL DEFAULT 0,
                    block_shows_empty bigint NOT NULL DEFAULT 0,
                    profit numeric(16,4) NOT NULL DEFAULT 0,
                    buyers_expense numeric(16,4) NOT NULL DEFAULT 0,
                    parent_profit numeric(16,4) NOT NULL DEFAULT 0,
                    agent_profit numeric(16,4) NOT NULL DEFAULT 0,

                    src_shows_for_delta bigint NOT NULL DEFAULT 0,
                    src_clicks_for_delta bigint NOT NULL DEFAULT 0,
                    buy_shows_for_delta bigint NOT NULL DEFAULT 0,
                    buy_clicks_for_delta bigint NOT NULL DEFAULT 0,
                    block_shows_for_delta bigint NOT NULL DEFAULT 0,
                    profit_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                    buyers_expense_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                    parent_profit_for_delta numeric(16,4) NOT NULL DEFAULT 0,
                    agent_profit_for_delta numeric(16,4) NOT NULL DEFAULT 0
                )
            ";
            $sth = $db->prepare($query);
            $sth->execute();
        }
    }


    /**
     * Заполняет временную таблицу сайтов/юзеров
     *
     */
    private function fillTempTableSites()
    {
        global $db;

        // Заполняем таблицу айдишниками сайтов, у которых есть статистика за расчетный период
        $query = "
            INSERT INTO tmp_sites_users(site_id)
            SELECT
                DISTINCT(site_id)
            FROM stats.day_sell
            WHERE (day >= ? AND day <= ?) OR
            (day >= ? AND day <= ?)
        ";
        $params = array($this->date_start, $this->date_end, $this->date_start_prev, $this->date_end_prev);
        $sth = $db->prepare($query);
        $sth->execute($params);

        // Заполняем таблицу остальными данными

        // Данные сайтов
        $query = "
            UPDATE tmp_sites_users AS tsu
            SET
                site_name = s.name,
                site_url = s.url,
                user_id = s.seller_id,
                country_id = s.country_id,
                profit_type = s.profit_type,
                min_teaser_rating = s.min_teaser_rating
            FROM da.sites AS s
            WHERE s.id = tsu.site_id
        ";
        $sth = $db->prepare($query);
        $sth->execute();

        // Данные юзеров
        // NOTE: маркетинговые метки здесь не заполняем, чтобы не усложнять запрос.
        // Выдергивать их будем, только если по ним запросили фильтрацию.
        $query = "
            UPDATE tmp_sites_users AS tsu
            SET
                email = u.email,
                subsystem_id = u.subsystem_id,
                is_exchanger = u.is_exchanger,
                is_disabled = u.is_disabled
            FROM da.users AS u
            WHERE u.id = tsu.user_id
        ";
        $sth = $db->prepare($query);
        $sth->execute();
    }


    /**
     * Заполняет таблицы статистики
     *
     */
    private function fillTempStats()
    {
        if ($this->tmp_stats_filled) {
            // Таблицы уже наполнены
            return;
        }

        // Заполняем стату за текущий период
        $this->fillTempTableStats('tmp_stats', $this->date_start, $this->date_end);

        // Заполняем стату за предыдущий период
        $this->fillTempTableStats('tmp_stats_prev', $this->date_start_prev, $this->date_end_prev);

        $this->tmp_stats_filled = true;
    }


    /**
     * Заполняет таблицу со статистикой за расчетный период
     *
     */
    private function fillTempTableStats($table, $date_start, $date_end)
    {
        global $db;

        $query = "
            INSERT INTO " . $table . "(
                site_id,
                src_shows,
                src_clicks,
                buy_shows,
                buy_clicks,
                block_shows,
                block_shows_empty,
                profit,
                buyers_expense,
                parent_profit,
                agent_profit
            )
            SELECT
                stats.site_id,
                COALESCE(SUM(stats.src_shows), 0) AS src_shows,
                COALESCE(SUM(stats.src_clicks), 0) AS src_clicks,
                COALESCE(SUM(stats.buy_shows), 0) AS buy_shows,
                COALESCE(SUM(stats.buy_clicks), 0) AS buy_clicks,
                COALESCE(SUM(stats.block_shows), 0) AS block_shows,
                COALESCE(SUM(stats.block_shows_empty), 0) AS block_shows_empty,
                COALESCE(SUM(stats.profit), 0) AS profit,
                COALESCE(SUM(stats.buyers_expense), 0) AS buyers_expense,
                COALESCE(SUM(stats.parent_profit), 0) AS parent_profit,
                COALESCE(SUM(stats.agent_profit), 0) AS agent_profit
            FROM stats.day_sell AS stats
                INNER JOIN tmp_sites_users AS s ON (s.site_id = stats.site_id)
            WHERE stats.day >= :date_start::date
                AND stats.day <= :date_end::date
            GROUP BY stats.site_id
        ";
        $params = array(
            'date_start'    => $date_start,
            'date_end'      => $date_end,
        );

        $sth = $db->prepare($query);
        $sth->execute($params);

        // Заполняем поля для расчета дельт.
        // Если последняя дата выборки - сегодня (выставлен флаг is_today_included),
        // дельты за сегодня по сравнению со вчера надо считать по полным завершенным часам.
        // Иначе - поля для дельт совпадают с основными полями.
        if ($this->is_today_included) {
            $params = array(
                'date_start'    => $date_start,
                'date_end'      => $date_end,
            );
            $query = "
                UPDATE " . $table . " AS ts
                SET
                    src_shows_for_delta = stats.src_shows,
                    src_clicks_for_delta = stats.src_clicks,
                    buy_shows_for_delta = stats.buy_shows,
                    buy_clicks_for_delta = stats.buy_clicks,
                    block_shows_for_delta = stats.block_shows,
                    profit_for_delta = stats.profit,
                    buyers_expense_for_delta = stats.buyers_expense,
                    parent_profit_for_delta = stats.parent_profit,
                    agent_profit_for_delta = stats.agent_profit
                FROM (
                    SELECT
                        stats.site_id,
                        COALESCE(SUM(stats.src_shows), 0) AS src_shows,
                        COALESCE(SUM(stats.src_clicks), 0) AS src_clicks,
                        COALESCE(SUM(stats.buy_shows), 0) AS buy_shows,
                        COALESCE(SUM(stats.buy_clicks), 0) AS buy_clicks,
                        COALESCE(SUM(stats.block_shows), 0) AS block_shows,
                        COALESCE(SUM(stats.profit), 0) AS profit,
                        COALESCE(SUM(stats.buyers_expense), 0) AS buyers_expense,
                        COALESCE(SUM(stats.parent_profit), 0) AS parent_profit,
                        COALESCE(SUM(stats.agent_profit), 0) AS agent_profit
                    FROM stats.day_sell AS stats
                        INNER JOIN tmp_sites_users AS s ON (s.site_id = stats.site_id)
                    WHERE stats.day >= :date_start::date
                        AND stats.day < :date_end::date
                    GROUP BY stats.site_id
                ) AS stats
                WHERE ts.site_id = stats.site_id
            ";

            $sth = $db->prepare($query);
            $sth->execute($params);

            // Добиваем данные статой за завершенные часы на последний день окончания периода выборки
            $params = array(
                'hour_start'    => $date_end . ' 00:00:00',
                'hour_end'      => $date_end . date(' H:00:00'),
            );
            $query = "
                UPDATE " . $table . " AS ts
                SET
                    src_shows_for_delta = src_shows_for_delta + stats.src_shows,
                    src_clicks_for_delta = src_clicks_for_delta + stats.src_clicks,
                    buy_shows_for_delta = buy_shows_for_delta + stats.buy_shows,
                    buy_clicks_for_delta = buy_clicks_for_delta + stats.buy_clicks,
                    block_shows_for_delta = block_shows_for_delta + stats.block_shows,
                    profit_for_delta = profit_for_delta + stats.profit,
                    buyers_expense_for_delta = buyers_expense_for_delta + stats.buyers_expense,
                    parent_profit_for_delta = parent_profit_for_delta + stats.parent_profit,
                    agent_profit_for_delta = agent_profit_for_delta + stats.agent_profit
                FROM (
                    SELECT
                        stats.site_id,
                        COALESCE(SUM(stats.src_shows), 0) AS src_shows,
                        COALESCE(SUM(stats.src_clicks), 0) AS src_clicks,
                        COALESCE(SUM(stats.buy_shows), 0) AS buy_shows,
                        COALESCE(SUM(stats.buy_clicks), 0) AS buy_clicks,
                        COALESCE(SUM(stats.block_shows), 0) AS block_shows,
                        COALESCE(SUM(stats.profit), 0) AS profit,
                        COALESCE(SUM(stats.buyers_expense), 0) AS buyers_expense,
                        COALESCE(SUM(stats.parent_profit), 0) AS parent_profit,
                        COALESCE(SUM(stats.agent_profit), 0) AS agent_profit
                    FROM stats.hour_sell AS stats
                        INNER JOIN tmp_sites_users AS s ON (s.site_id = stats.site_id)
                    WHERE stats.hour >= :hour_start::timestamp
                        AND stats.hour < :hour_end::timestamp
                    GROUP BY stats.site_id
                ) AS stats
                WHERE ts.site_id = stats.site_id
            ";
        } else {
            $params = array();
            $query = "
                UPDATE " . $table . "
                SET
                    src_shows_for_delta = src_shows,
                    src_clicks_for_delta = src_clicks,
                    buy_shows_for_delta = buy_shows,
                    buy_clicks_for_delta = buy_clicks,
                    block_shows_for_delta = block_shows,
                    profit_for_delta = profit,
                    buyers_expense_for_delta = buyers_expense,
                    parent_profit_for_delta = parent_profit,
                    agent_profit_for_delta = agent_profit
            ";
        }

        $sth = $db->prepare($query);
        $sth->execute($params);
    }


    /**
     * Заполняет полную таблицу отчета
     *
     */
    private function fillTempTableReport()
    {
        global $db;

        if ($this->tmp_report_filled) {
            return;
        }

        // 0. Предварительно заполняем таблицу айдишниками
        $query = "
            INSERT INTO tmp_report (id)
            SELECT DISTINCT(" . ($this->group_by_publisher ? 'user_id' : 'site_id') . ") FROM tmp_sites_users
        ";
        $sth = $db->prepare($query);
        $sth->execute();

        // 1. Наполняем отчет основными данными с учетом группировки
        $query = "
            UPDATE tmp_report AS tr
            SET
                name = stats.name,
                src_shows = stats.src_shows,
                src_clicks = stats.src_clicks,
                buy_shows = stats.buy_shows,
                buy_clicks = stats.buy_clicks,
                block_shows = stats.block_shows,
                block_shows_empty = stats.block_shows_empty,
                profit = stats.profit,
                system_profit = stats.system_profit,
                percent_system_profit = CASE WHEN stats.site_raw_profit <> 0 THEN
                    100 * stats.system_profit / stats.site_raw_profit ELSE 0 END,

                src_shows_for_delta = stats.src_shows_for_delta,
                src_clicks_for_delta = stats.src_clicks_for_delta,
                buy_shows_for_delta = stats.buy_shows_for_delta,
                buy_clicks_for_delta = stats.buy_clicks_for_delta,
                block_shows_for_delta = stats.block_shows_for_delta,
                profit_for_delta = stats.profit_for_delta,
                system_profit_for_delta = stats.system_profit_for_delta,
                percent_system_profit_for_delta = CASE WHEN stats.site_raw_profit_for_delta <> 0 THEN
                    100 * stats.system_profit_for_delta / stats.site_raw_profit_for_delta ELSE 0 END
            FROM (
                SELECT
                    tsu." . ($this->group_by_publisher ? 'user_id' : 'site_id') . " AS id,
                    tsu." . ($this->group_by_publisher ? 'email' : 'site_name') . " AS name,
                    SUM(ts.src_shows) AS src_shows,
                    SUM(ts.src_clicks) AS src_clicks,
                    SUM(ts.buy_shows) AS buy_shows,
                    SUM(ts.buy_clicks) AS buy_clicks,
                    SUM(ts.block_shows) AS block_shows,
                    SUM(ts.block_shows_empty) AS block_shows_empty,
                    SUM(ts.profit) AS profit,
                    SUM(ts.buyers_expense) - SUM(ts.profit) - SUM(ts.parent_profit) -
                        SUM(ts.agent_profit) AS system_profit,
                    SUM(ts.buyers_expense) - SUM(ts.parent_profit) - SUM(ts.agent_profit) AS site_raw_profit,

                    SUM(ts.src_shows_for_delta) AS src_shows_for_delta,
                    SUM(ts.src_clicks_for_delta) AS src_clicks_for_delta,
                    SUM(ts.buy_shows_for_delta) AS buy_shows_for_delta,
                    SUM(ts.buy_clicks_for_delta) AS buy_clicks_for_delta,
                    SUM(ts.block_shows_for_delta) AS block_shows_for_delta,
                    SUM(ts.profit_for_delta) AS profit_for_delta,
                    SUM(ts.buyers_expense_for_delta) - SUM(ts.profit_for_delta) - SUM(ts.parent_profit_for_delta) -
                        SUM(ts.agent_profit_for_delta) AS system_profit_for_delta,
                    SUM(ts.buyers_expense_for_delta) - SUM(ts.parent_profit_for_delta) -
                        SUM(ts.agent_profit_for_delta) AS site_raw_profit_for_delta
                FROM tmp_stats AS ts
                    INNER JOIN tmp_sites_users AS tsu ON (tsu.site_id = ts.site_id)
                GROUP BY 1,2
            ) AS stats
            WHERE tr.id = stats.id
        ";

        $sth = $db->prepare($query);
        $sth->execute();

        // 2. Вычисляем cpm, cpc и проч.
        $query = "
            UPDATE tmp_report
            SET
                src_ctr = ctr(src_shows, src_clicks),
                src_click_price = cpc(src_clicks, profit),
                src_cpm = cpm(src_shows, profit),

                buy_ctr = ctr(buy_shows, buy_clicks),
                buy_click_price = cpc(buy_clicks, profit),
                buy_cpm = cpm(buy_shows, profit),

                block_ctr = ctr(block_shows, src_clicks),
                block_cpm = cpm(block_shows, profit),
                effective_block_shows_pc = CASE
                            WHEN block_shows > 0
                            THEN ((block_shows - block_shows_empty)::bigint * 100)::float / block_shows
                            ELSE 0
                        END,

                src_ctr_for_delta = ctr(src_shows_for_delta, src_clicks_for_delta),
                src_click_price_for_delta = cpc(src_clicks_for_delta, profit_for_delta),
                src_cpm_for_delta = cpm(src_shows_for_delta, profit_for_delta),

                buy_ctr_for_delta = ctr(buy_shows_for_delta, buy_clicks_for_delta),
                buy_click_price_for_delta = cpc(buy_clicks_for_delta, profit_for_delta),
                buy_cpm_for_delta = cpm(buy_shows_for_delta, profit_for_delta),

                block_ctr_for_delta = ctr(block_shows_for_delta, src_clicks_for_delta),
                block_cpm_for_delta = cpm(block_shows_for_delta, profit_for_delta)
        ";
        $sth = $db->prepare($query);
        $sth->execute();

        // 4. Заполняем дельты
        $this->calcDeltas();

        $this->tmp_report_filled = true;
    }


    /**
     * Вычисляет дельты по сравнению с предыдущим периодом
     *
     */
    private function calcDeltas()
    {
        global $db;

        $query = "
            UPDATE tmp_report AS tr
            SET
                delta_src_shows = tr.src_shows_for_delta - stats_prev.src_shows_for_delta,
                delta_src_clicks = tr.src_clicks_for_delta - stats_prev.src_clicks_for_delta,
                delta_src_ctr = tr.src_ctr_for_delta -
                    ctr(stats_prev.src_shows_for_delta, stats_prev.src_clicks_for_delta),
                delta_src_click_price = tr.src_click_price_for_delta -
                    cpc(stats_prev.src_clicks_for_delta, stats_prev.profit_for_delta),
                delta_src_cpm = tr.src_cpm_for_delta - cpm(stats_prev.src_shows_for_delta, stats_prev.profit_for_delta),

                delta_buy_shows = tr.buy_shows_for_delta - stats_prev.buy_shows_for_delta,
                delta_buy_clicks = tr.buy_clicks_for_delta - stats_prev.buy_clicks_for_delta,
                delta_buy_ctr = tr.buy_ctr_for_delta -
                    ctr(stats_prev.buy_shows_for_delta, stats_prev.buy_clicks_for_delta),
                delta_buy_click_price = tr.buy_click_price_for_delta -
                    cpc(stats_prev.buy_clicks_for_delta, stats_prev.profit_for_delta),
                delta_buy_cpm = tr.buy_cpm_for_delta - cpm(stats_prev.buy_shows_for_delta, stats_prev.profit_for_delta),

                delta_block_shows = tr.block_shows_for_delta - stats_prev.block_shows_for_delta,
                delta_block_ctr = tr.block_ctr_for_delta -
                    ctr(stats_prev.block_shows_for_delta, stats_prev.src_clicks_for_delta),
                delta_block_cpm = tr.block_cpm_for_delta -
                    cpm(stats_prev.block_shows_for_delta, stats_prev.profit_for_delta),

                delta_profit = tr.profit_for_delta - stats_prev.profit_for_delta,

                delta_system_profit = tr.system_profit_for_delta - stats_prev.system_profit_for_delta,
                delta_percent_system_profit = tr.percent_system_profit_for_delta -
                    (CASE WHEN stats_prev.site_raw_profit_for_delta <> 0 THEN
                        100 * stats_prev.system_profit_for_delta / stats_prev.site_raw_profit_for_delta ELSE 0 END)
            FROM (
                SELECT
                    tsu." . ($this->group_by_publisher ? 'user_id' : 'site_id') . " AS id,
                    COALESCE(SUM(ts.src_shows_for_delta), 0) AS src_shows_for_delta,
                    COALESCE(SUM(ts.src_clicks_for_delta), 0) AS src_clicks_for_delta,
                    COALESCE(SUM(ts.buy_shows_for_delta), 0) AS buy_shows_for_delta,
                    COALESCE(SUM(ts.buy_clicks_for_delta), 0) AS buy_clicks_for_delta,
                    COALESCE(SUM(ts.block_shows_for_delta), 0) AS block_shows_for_delta,
                    COALESCE(SUM(ts.profit_for_delta), 0) AS profit_for_delta,
                    COALESCE(SUM(ts.buyers_expense_for_delta), 0) - COALESCE(SUM(ts.profit_for_delta), 0) -
                        COALESCE(SUM(ts.parent_profit_for_delta), 0) -
                            COALESCE(SUM(ts.agent_profit_for_delta), 0) AS system_profit_for_delta,
                    COALESCE(SUM(ts.buyers_expense_for_delta), 0) - COALESCE(SUM(ts.parent_profit_for_delta), 0) -
                        COALESCE(SUM(ts.agent_profit_for_delta), 0) AS site_raw_profit_for_delta
                FROM tmp_stats_prev AS ts
                    RIGHT JOIN tmp_sites_users AS tsu ON (tsu.site_id = ts.site_id)
                GROUP BY 1
            ) AS stats_prev
            WHERE tr.id = stats_prev.id
        ";
        $sth = $db->prepare($query);
        $sth->execute();
    }


    /**
     * Устанавливает фильтр по стране
     *
     * @param int $country_id
     */
    public function setCountry($country_id)
    {
        $this->filter->byCountry($country_id);
    }


    /**
     * Устанавливает фильтр по подсистеме
     *
     * @param int $subsystem
     */
    public function setSubsystemFilter($subsystem)
    {
        $this->filter->bySubsystem($subsystem);
    }


    /**
     * Устанавливает фильтр по группе менеджеров и её подгруппам
     *
     * @param int $group_id
     */
    public function setManagersGroup($group_id)
    {
        $managers = $this->admin->getManagersList(false, $group_id);
        if ($managers) {
            // Если доступен список менеджеров по заданной группе
            $this->managers = array();
            foreach ($managers as $gm) {
                $this->managers[] = $gm['id'];
            }
        }

        $this->filter->byManagers($this->managers);
    }


    /**
     * Устанавливает фильтр по менеджеру.
     *
     * @param int $manager_id
     */
    public function setManager($manager_id)
    {
        $manager_id = (int) $manager_id;
        if (empty($manager_id)) {
            return;
        }

        if ($this->admin->has_role_root || $this->admin->has_role_advanced_support) {
            // Админы могут фильтровать юзеров по любому менеджеру
            $this->managers = array($manager_id);
        } elseif ($this->admin->is_group_main) {
            // Старшие группы могут фильтровать юзеров по менеджерам доступных групп
            foreach ($this->admin->getManagersList(false) as $gm) {
                if ($manager_id == $gm['id']) {
                    $this->managers = array($manager_id);
                }
            }
        }

        $this->filter->byManagers($this->managers);
    }


    /**
     * Устанавливает фильтр по св-ву обменник/необменник
     * Параметр $filter имеет 3 значения - пустая строка,
     * 'not_exchanger' - делаем запросы только по пользователям, которые не являются обменниками
     * 'exchanger' - делаем запросы только по обменникам траффика
     *
     * @param string $filter
     */
    public function setExchangerFilter($filter)
    {
        $this->filter->byExchanger($filter);
    }


    /**
     * Устанавливает фильтр по E-mail/ID пользователя
     *
     * @param $filter
     */
    public function setUserFilter($filter)
    {
        $is_simple_support = $this->admin->has_role_support
            && !$this->admin->has_role_advanced_support
            && empty($this->managers);
        $this->filter->byUser($filter, $is_simple_support);
    }


    /**
     * Устанавливает фильтр по префиксу email пользователя (для NNN, BeaverAds, Nytive) #21489
     *
     * @param $filter
     */
    public function setNetworkFilter($filter)
    {
        $this->filter->byNetwork($filter);
    }


    /**
     * Устанавливает фильтр по URL/ID сайта
     *
     * @param $filter
     */
    public function setSiteFilter($filter)
    {
        $this->filter->bySite($filter);
    }


    /**
     * Устанавливает фильтр по marketing marks
     *
     * @param array $filter_mark
     */
    public function setMarkFilter($filter_mark)
    {
        $this->filter->byMark($filter_mark, $this->filter_available_marks);
    }


    /**
     * Устанавливает фильтр для сайтов по типам профита
     *
     * @param array $profit_types - Массив с типами профитов
     */
    public function setSitesProfitTypes(array $profit_types)
    {
        $this->filter->bySitesProfitTypes($profit_types);
    }


    /**
     * #13628: Установка фильтра по статусу паблишера (на самом деле сайта) - "новый" или "текущий".
     * "Новым" считается сайт, у которого в течение 2-х месяцев до текущего месяца не было трафика.
     * "Текущим" - у которого в течение 2-х месяцев до текущего месяца есть трафик.
     * Под текущим месяцем здесь понимается текущий выбранный в отчете месяц, а не тот, который сейчас на календаре.
     * @param string$ publisher_status - статус паблишера (new, current)
     *
     * @return void
     **/
    public function setPublisherStatus($publisher_status)
    {
        $this->filter->byPublisherStatus($publisher_status, $this->date_start);
    }


    /**
     * Установка фильтра по минимальному рейтингу объявления
     * @param array $min_teaser_rating - минимальный рейтинг объявления (null, 1-5)
     *
     * @return void
     **/
    public function setMinTeaserRating($min_teaser_rating)
    {
        $this->filter->byMinTeaserRating($min_teaser_rating);
    }


    /**
     * Установка фильтра по категории сайта
     * @param array $site_category - ID категории сайта
     *
     * @return void
     **/
    public function setSiteCategory($site_category)
    {
        $this->filter->bySiteCategory($site_category);
    }


    /**
     * #13324: При группировке отчета по сайтам при наводе на название сайта
     * выводим панель с дополнительной информацией
     *
     * @param array $report
     */
    private function addAdditionalInfo(&$report)
    {
        global $db;

        if ($this->group_by_publisher) {
            // При группировке по паблишерам панель не нужна
            return;
        }

        // Панель формируется двумя блоками. Блоки были раскиданы - один в модели, другой в контроллере.
        // Вызывались всегда, хотя нужны только при группировке по сайтам
        // Перенесено как есть, более-менее почищено от мусора, но требует рефакторинга

        // Первая часть
        $ids = array();
        foreach ($report as $key => $row) {
            $ids[] = $row['id'];
        }

        if (!empty($ids)) {
            $user_query = "
                SELECT
                    s.id,
                    s.min_teaser_rating,
                    s.min_ad_price,
                    ARRAY_LENGTH(s.buyer_target, 1) AS buyer_target
                FROM da.sites AS s
                WHERE s.id IN (" . trim(str_repeat('?,', count($ids)), ',') . ")
                ";
            $params = $ids;
            $sth = $db->prepare($user_query);
            $sth->execute($params);
            $user_st = $sth->fetchAll(\PDO::FETCH_ASSOC);
            $user_stats = array();
            foreach ($user_st as $key => $row) {
                $user_stats[$row['id']] = $row;
            }

            foreach ($report as $key => $row) {
                $report[$key] = array_merge($report[$key], $user_stats[$row['id']]);
            }
        }

        // Вторая часть
        foreach ($report as $key => $val) {
            $filter = new \Da_FilterThemes('site', $val['id']);
            $news_themes = $filter->getNewsThemes();
            $news_themes_not_filtered = 0;
            $news_themes_total = count($news_themes);
            if ($news_themes_total) {
                foreach ($news_themes as $news_theme) {
                    if (!$news_theme['is_filtered']) {
                        $news_themes_not_filtered++;
                    }
                }
            }
            $report[$key]['news_themes_not_filtered'] = $news_themes_not_filtered;
            $report[$key]['news_themes_total'] = $news_themes_total;

            $user_themes = $filter->getUserThemes();
            $user_themes_not_filtered = 0;
            $user_themes_total = count($user_themes);
            if ($user_themes_total) {
                foreach ($user_themes as $user_theme) {
                    if (!$user_theme['is_filtered']) {
                        $user_themes_not_filtered++;
                    }
                }
            }
            $report[$key]['user_themes_not_filtered'] = $user_themes_not_filtered;
            $report[$key]['user_themes_total'] = $user_themes_total;

            $User = new \Da_User();
            $User->id = $val['user_id'];
            $User->selected_partner_site_id = $val['id'];
            $FilterAds = new \Da_FilterAds($User);
            $report[$key]['filtered_buyers'] = $FilterAds->getBlockedBuyersCount();
        }
    }


    /**
     *
     * @global object $db
     * @param int $current_page
     * @param int $items_on_page
     * @param string $order
     * @return array
     */
    public function report($current_page = 1, $items_on_page = 50, $order = 'profit', $desc = false)
    {
        global $Config, $db;

        // Заполняем таблицы статистик за расчетный период и предыдущий
        $this->fillTempStats();
        // Заполняем таблицу отчета
        $this->fillTempTableReport();

        $params = array();

        // Сортировка
        $sorts = array(
            'name', 'profit', 'system_profit', 'percent_system_profit',
            'src_shows', 'src_clicks', 'src_ctr', 'src_click_price',
            'buy_shows', 'buy_clicks', 'buy_ctr', 'buy_click_price',
            'src_cpm', 'delta_profit', 'delta_system_profit', 'delta_percent_system_profit',
            'delta_src_shows', 'delta_src_clicks', 'delta_src_ctr', 'delta_src_click_price',
            'delta_buy_shows', 'delta_buy_clicks', 'delta_buy_ctr', 'delta_buy_click_price',
            'delta_src_cpm', 'block_shows', 'delta_block_shows', 'block_cpm', 'delta_block_cpm',
            'block_ctr', 'delta_block_ctr',
            'effective_block_shows_pc',
        );
        if (!in_array($order, $sorts)) {
            $order = 'profit';
        }
        $order = 'r.' . $order;
        $order .= ($desc ? ' DESC' : '');

        // LIMIT
        if ($items_on_page > 0) {
            $params['limit'] = $items_on_page;
            $offset = ($current_page - 1) * $items_on_page;
            if ($offset < 0) {
                $offset = 0;
            }
            $params['offset'] = $offset;
        }

        // Выводим отчет с учетом группировки по сайту или по паблишеру
        if ($this->group_by_publisher) {
            // Основной отчет сгруппирован по паблишерам
            $inner_join = "
                INNER JOIN da.users AS u ON (u.id = r.id)
            ";
        } else {
            // Основной отчет сгруппирован по сайтам
            $inner_join = "
                INNER JOIN da.sites AS s ON (s.id = r.id)
                INNER JOIN da.users AS u ON (u.id = s.seller_id)
            ";
        }

        $query = "
            SELECT
                r.id,
                " . ($this->group_by_publisher ? '' : 's.id AS site_id,') . "
                " . ($this->group_by_publisher ? 'u.email AS name' :
                        (INSTANCE_YENGO || INSTANCE_BEAVER ? 's.url AS name' : 's.name')) . ",
                u.id AS user_id,
                u.is_disabled,
                u.is_exchanger,

                r.profit,
                r.delta_profit,

                r.system_profit,
                r.delta_system_profit,

                r.percent_system_profit,
                r.delta_percent_system_profit,

                r.src_shows,
                r.delta_src_shows,
                r.src_clicks,
                r.delta_src_clicks,
                r.src_ctr,
                r.delta_src_ctr,
                r.src_click_price,
                r.delta_src_click_price,
                r.src_cpm,
                r.delta_src_cpm,

                r.buy_shows,
                r.delta_buy_shows,
                r.buy_clicks,
                r.delta_buy_clicks,
                r.buy_ctr,
                r.delta_buy_ctr,
                r.buy_click_price,
                r.delta_buy_click_price,
                r.buy_cpm,
                r.delta_buy_cpm,

                r.block_shows,
                r.delta_block_shows,
                r.block_ctr,
                r.delta_block_ctr,
                r.block_cpm,
                r.delta_block_cpm,
                r.block_shows_empty,
                r.effective_block_shows_pc
            FROM tmp_report AS r
                " . $inner_join . "
            ORDER BY " . $order . "
        ";
        if ($items_on_page > 0) {
            $query .= "LIMIT :limit OFFSET :offset";
        }

        $sth = $db->prepare($query);
        $sth->execute($params);

        $report = $sth->fetchAll(\PDO::FETCH_ASSOC);

        // #13324: При группировке отчета по сайтам при наводе на название сайта
        // выводим панель с дополнительной информацией
        $this->addAdditionalInfo($report);

        return $report;
    }


    /**
     * Суммы показателей за весь выбранный период.
     *
     * @global DB $db
     * @return array
     */
    public function getTotal()
    {
        global $Config, $db;

        // Заполняем таблицы статистик за расчетный период и предыдущий
        $this->fillTempStats();
        // Заполняем таблицу отчета
        $this->fillTempTableReport();

        $query = "
            SELECT
                SUM(profit) AS profit,
                SUM(delta_profit) AS delta_profit,

                SUM(system_profit) AS system_profit,
                SUM(delta_system_profit) AS delta_system_profit,

                SUM(percent_system_profit) AS percent_system_profit,
                SUM(delta_percent_system_profit) AS delta_percent_system_profit,

                SUM(src_shows) AS src_shows,
                SUM(delta_src_shows) AS delta_src_shows,
                SUM(src_clicks) AS src_clicks,
                SUM(delta_src_clicks) AS delta_src_clicks,

                ctr(SUM(src_shows), SUM(src_clicks)) AS src_ctr,
                ctr(SUM(src_shows_for_delta), SUM(src_clicks_for_delta))
                - ctr(SUM(src_shows_for_delta - delta_src_shows), SUM(src_clicks_for_delta - delta_src_clicks))
                AS delta_src_ctr,

                cpc(SUM(src_clicks), SUM(profit)) AS src_click_price,
                cpc(SUM(src_clicks_for_delta), SUM(profit_for_delta))
                - cpc(SUM(src_clicks_for_delta - delta_src_clicks), SUM(profit_for_delta - delta_profit))
                AS delta_src_click_price,

                cpm(SUM(src_shows), SUM(profit)) AS src_cpm,
                cpm(SUM(src_shows_for_delta), SUM(profit_for_delta))
                -
                cpm(SUM(src_shows_for_delta - delta_src_shows), SUM(profit_for_delta - delta_profit))
                AS delta_src_cpm,

                SUM(buy_shows) AS buy_shows,
                SUM(delta_buy_shows) AS delta_buy_shows,
                SUM(buy_clicks) AS buy_clicks,
                SUM(delta_buy_clicks) AS delta_buy_clicks,

                ctr(SUM(buy_shows), SUM(buy_clicks)) AS buy_ctr,
                ctr(SUM(buy_shows_for_delta), SUM(buy_clicks_for_delta))
                - ctr(SUM(buy_shows_for_delta - delta_buy_shows), SUM(buy_clicks_for_delta - delta_buy_clicks))
                AS delta_buy_ctr,

                cpc(SUM(buy_clicks), SUM(profit)) AS buy_click_price,
                cpc(SUM(buy_clicks_for_delta), SUM(profit_for_delta))
                - cpc(SUM(buy_clicks_for_delta - delta_buy_clicks), SUM(profit_for_delta - delta_profit))
                AS delta_buy_click_price,

                cpm(SUM(buy_shows), SUM(profit)) buy_cpm,
                cpm(SUM(buy_shows_for_delta), SUM(profit_for_delta))
                -
                cpm(SUM(buy_shows_for_delta - delta_buy_shows), SUM(profit_for_delta - delta_profit))
                AS delta_buy_cpm,

                SUM(block_shows) AS block_shows,
                SUM(delta_block_shows) AS delta_block_shows,

                ctr(SUM(block_shows), SUM(src_clicks)) AS block_ctr,
                ctr(SUM(block_shows_for_delta), SUM(src_clicks_for_delta))
                - ctr(SUM(block_shows_for_delta - delta_block_shows), SUM(src_clicks_for_delta - delta_src_clicks))
                AS delta_block_ctr,

                cpm(SUM(block_shows), SUM(profit)) AS block_cpm,
                cpm(SUM(block_shows_for_delta), SUM(profit_for_delta))
                -
                cpm(SUM(block_shows_for_delta - delta_block_shows), SUM(profit_for_delta - delta_profit))
                AS delta_block_cpm,

                CASE
                    WHEN SUM(block_shows) > 0
                    THEN ((SUM(block_shows - block_shows_empty))::bigint * 100)::float / SUM(block_shows)
                    ELSE 0
                END AS effective_block_shows_pc

            FROM tmp_report
        ";

        $sth = $db->prepare($query);
        $sth->execute();

        $total = $sth->fetch(\PDO::FETCH_ASSOC);
        $total['percent_system_profit'] = $this->percentSysProfit($total['system_profit'], $total['profit']);
        $total['delta_percent_system_profit'] = $this->percentSysProfit($total['system_profit'], $total['profit'])
            - $this->percentSysProfit(
                $total['system_profit'] - $total['delta_system_profit'],
                $total['profit'] - $total['delta_profit']
            );

        return $total;
    }


    /**
     * Сколько всего записей на заданный период.
     *
     * @global object $db
     * @return int
     */
    public function getCount()
    {
        global $db;

        // Заполняем таблицы статистик за расчетный период и предыдущий
        $this->fillTempStats();
        // Заполняем таблицу отчета
        $this->fillTempTableReport();

        $query = "SELECT COUNT(*) FROM tmp_report";

        $sth = $db->prepare($query);
        $sth->execute();

        return $sth->fetchColumn();
    }


    /**
     * Подсчитывает страничный итог, суммируя строки отчёта
     *
     * @param array $report
     * @return array
     */
    public function getPageTotal($report)
    {
        $page_total = \Da\Std::arraySumByKeys($report);

        $page_total['percent_system_profit'] =
                $this->percentSysProfit($page_total['system_profit'], $page_total['profit']);
        $page_total['delta_percent_system_profit'] =
                $this->percentSysProfit($page_total['system_profit'], $page_total['profit']) -
                $this->percentSysProfit(
                    $page_total['system_profit'] - $page_total['delta_system_profit'],
                    $page_total['profit'] - $page_total['delta_profit']
                );

        $page_total['src_ctr']       = $this->ctr($page_total['src_shows'], $page_total['src_clicks']);
        $page_total['delta_src_ctr'] =
                $this->delta2p(
                    $page_total['src_clicks'],
                    $page_total['delta_src_clicks'],
                    $page_total['src_shows'],
                    $page_total['delta_src_shows']
                ) * 100;

        $page_total['src_click_price']       = $this->cpc($page_total['src_clicks'], $page_total['profit']);
        $page_total['delta_src_click_price'] =
                $this->delta2p(
                    $page_total['profit'],
                    $page_total['delta_profit'],
                    $page_total['src_clicks'],
                    $page_total['delta_src_clicks']
                );

        $page_total['src_cpm']       = $this->cpm($page_total['src_shows'], $page_total['profit']);
        $page_total['delta_src_cpm'] =
                $this->delta2p(
                    $page_total['profit'],
                    $page_total['delta_profit'],
                    $page_total['src_shows'],
                    $page_total['delta_src_shows']
                ) * 1000;

        $page_total['block_cpm']     = $this->cpm($page_total['block_shows'], $page_total['profit']);
        $page_total['delta_block_cpm'] =
                $this->delta2p(
                    $page_total['profit'],
                    $page_total['delta_profit'],
                    $page_total['block_shows'],
                    $page_total['delta_block_shows']
                ) * 1000;

        $page_total['block_ctr']       = $this->ctr($page_total['block_shows'], $page_total['src_clicks']);
        $page_total['delta_block_ctr'] =
                $this->delta2p(
                    $page_total['src_clicks'],
                    $page_total['delta_src_clicks'],
                    $page_total['block_shows'],
                    $page_total['delta_block_shows']
                ) * 100;

        $page_total['effective_block_shows_pc'] =
                $page_total['block_shows'] > 0 ?
                    ($page_total['block_shows'] - $page_total['block_shows_empty']) * 100 /
                        $page_total['block_shows'] : 0;

        $page_total['buy_ctr']       = $this->ctr($page_total['buy_shows'], $page_total['buy_clicks']);
        $page_total['delta_buy_ctr'] =
                $this->delta2p(
                    $page_total['buy_clicks'],
                    $page_total['delta_buy_clicks'],
                    $page_total['buy_shows'],
                    $page_total['delta_buy_shows']
                ) * 100;

        $page_total['buy_click_price']       = $this->cpc($page_total['buy_clicks'], $page_total['profit']);
        $page_total['delta_buy_click_price'] =
                $this->delta2p(
                    $page_total['profit'],
                    $page_total['delta_profit'],
                    $page_total['buy_clicks'],
                    $page_total['delta_buy_clicks']
                );

        return $page_total;
    }
}
