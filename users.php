<?php
/*
 * $Id$
 */

// Тип пользователя
$available_user_types = array(
    'agent'     => 'Agent',
    'buyer'     => 'Advertiser',
    'seller'    => 'Publisher',
    'exchanger' => 'Exchanger'
);
$tpl->assign('user_types', $available_user_types);
$user_type = \Da\Std::grabSession('user_type', 'da_admin_users_user_type');

// Список подсистем
$Da_Subsystem = new \Da\System\Subsystem();
$subsystems_list = $Da_Subsystem->getList();
$tpl->assign('subsystems', $subsystems_list);
$tpl->assign('da_subsystems', $subsystems_list);

if (IS_DA_COUNTRY_ENABLED) {
    // Список стран
    $Da_Country = new \Da\System\Country();
    $tpl->assign('da_countries', $Da_Country->getList());
}

// Лимит возвращаемых записей в поиске
if (isset($available_user_types[$user_type])) {
    $page_items = 50;
} else {
    $page_items = 100;
    $order = 'email';
    $current_page = 1;
}
$tpl->assign('page_items', $page_items);

// Фильтры по Marketing marks
$filter_mark = (array)\Da\Std::grabSession('filter_mark', 'da_admin_users_mark_filter');
$tpl->assign('filter_mark', $filter_mark);

// Общие фильтры
$filter = (array)\Da\Std::grabSession('filter', 'da_admin_users_filter');

// Объединим, чтобы передать в класс один массив
$filter = array_merge($filter, $filter_mark);

// Отфильтруем только допустимые
$available_filters = array('email', 'subsystem', 'site', 'src_id', 'country', 'webmoney', 'crm_status', 'source', 'campaign', 'medium', 'ad');
$filter = array_intersect_key($filter, array_flip($available_filters));

$tpl->assign('filter', $filter);

// Селектор All accounts / My accounts / My group accounts / Manager's accounts
if ($Admin->has_role_root || $Admin->is_group_main || $Admin->has_role_advanced_support) {
    // Руту и старшему группы доступны фильтры по менеджеру или группе
    $managers = $Admin->getManagersList(true);
    $tpl->assign('managers', $managers);

    // Стандартное поведение all/my
    $available_manager_filter = array('my', 'all', 'manager');
    if ($Admin->is_group_main) {
        // Дополнительный пункт для старшего группы - "My group accounts"
        $available_manager_filter[] = 'my_group';
    }
    $manager_filter = \Da\Std::grabSession('manager_filter', 'da_admin_users_manager_filter', $available_manager_filter, 'all');
    $manager_id = (int)\Da\Std::grabSession('manager_id', 'da_admin_users_manager_id');

} else {
    // Стандартное поведение all/my для остальных
    $manager_filter = \Da\Std::grabSession('manager_filter', 'da_admin_users_manager_filter', array('my', 'all'), 'my');
}

// Первоначальное значение _переданного_ в $_GET менеджер-фильтра на случай,
// если оригинальный изменится в процессе этого экшена
// или не передан вообще (reset формы или первоначальная страница /users/)
$tpl->assign('manager_filter_ex', \Da\Std::grab('manager_filter', ''));

// Только что перешли на страницу и поиск ещё не производился
$action = \Da\Std::grab('action');
$is_first_show = ($action != 'search');
$tpl->assign('first_show', $is_first_show);

if (empty($filter)) {
    // Первый заход
    $tpl->assign('filter', false);
    $tpl->assign('manager_filter', $manager_filter);
    $tpl->assign('manager_id', null);
    $tpl->assign('current_type', $user_type);
    $tpl->assign('content', 'users');
    return;
}

\Da\User\Listing::validateFilter($filter);

if ($manager_filter == 'all' &&
    empty($filter['email']) && empty($filter['site']) && empty($filter['src_id']) &&
    empty($filter_mark['source']) && empty($filter_mark['campaign']) &&
    empty($filter_mark['medium']) && empty($filter_mark['ad']) &&
    empty($filter['webmoney'])) {
    // Если фильтр по мылу, сайту, источнику и marketing marks пустой - выводим только свои аккаунты
    $manager_filter = 'my';
}

if ($manager_filter == 'all') {
    // Выбраны все аккаунты - фильтр по менеджеру выключен
    $manager_id = null;
}

if ($manager_filter == 'my_group') {
    // Выбраны аккаунты своей и нижестоящих групп
    $allowed_managers = $Admin->getManagersList(false, $Admin->group_id);
    $manager_id = array();
    foreach ($allowed_managers as $man) {
        $manager_id[] = $man['id'];
    }
    if (empty($manager_id)) {
        $manager_filter = 'my';
    }
}

if ($manager_filter == 'my') {
    // Выбраны свои аккаунты - подставляем свой id в фильтр по менеджеру
    $manager_id = $Admin->id;
}

// Ищем среди агентов (НЕ ищем, если есть фильтр по сайту, ID источника, подсистеме или по CRM статусу)
if (!$is_first_show && empty($filter['site']) && empty($filter['src_id']) && empty($filter['subsystem']) && empty($filter['crm_status']) && (!$user_type || $user_type == 'agent')) {
    $Da_AgentsList = new Da\User\Agent\Listing($Admin, $manager_id);

    // Установка фильтров поиска
    foreach ($filter as $f => $value) {
        $Da_AgentsList->setListFilter($f, $value);
    }

    // Общее кол-во агентов в выборке
    $count = $Da_AgentsList->getCount();

    if ($user_type == 'agent') {
        // Поиск только по агентам - задействуем сортировку и постраничность
        $paginator  = new \Da\View\Paginator($page_items);

        $order = \Da\Std::grab('order');

        // Задаем кол-во агентов в листалке
        $paginator->setAllItemsCnt($count);
        $current_page = $paginator->getCurrentPage();

        $pages_cnt = $paginator->getAllPagesCnt();
        $tpl->assign('pages_cnt', $pages_cnt);
        $tpl->assign('current_page', $current_page);
        $tpl->assign('order', $order);

        if ($pages_cnt) {
            // Итоговые суммы для всей выборки
            $total  = $Da_AgentsList->getTotal();
            $tpl->assign('agents_total', $total);
        }
    }

    // Сами данные
    $data   = $Da_AgentsList->getPage($current_page, $page_items, $order);

    $tpl->assign('agents_list', $data);
    $tpl->assign('agents_count', $count);
}

// Ищем среди покупателей (НЕ ищем, если есть фильтр по сайту, ID источника или подсистеме)
if (!$is_first_show && empty($filter['site']) && empty($filter['src_id']) && empty($filter['subsystem']) && (!$user_type || $user_type == 'buyer')) {
    $UserListing = new \Da\User\Listing($Admin, $manager_id);

    // Установка фильтров поиска
    foreach ($filter as $f => $value) {
        $UserListing->setListFilter($f, $value);
    }

    // Общее кол-во покупателей в выборке
    $count = $UserListing->getBuyersCount();

    if ($user_type == 'buyer') {
        // Поиск только по покупателям - задействуем сортировку и постраничность
        $paginator  = new \Da\View\Paginator($page_items);

        $order = \Da\Std::grab('order');

        // Задаем кол-во покупателей в листалке
        $paginator->setAllItemsCnt($count);
        $current_page = $paginator->getCurrentPage();

        $pages_cnt = $paginator->getAllPagesCnt();
        $tpl->assign('pages_cnt', $pages_cnt);
        $tpl->assign('current_page', $current_page);
        $tpl->assign('order', $order);

        if ($pages_cnt > 1) {
            // Итоговые суммы для всей выборки
            $total  = $UserListing->getBuyersTotal();
            $tpl->assign('buyers_total', $total);
        }
    }

    // Сами данные
    $data = $UserListing->getBuyersPage($current_page, $page_items, $order);

    $tpl->assign('buyers_list', $data);
    $tpl->assign('buyers_count', $count);
}

// Ищем среди партнёров
if (!$is_first_show &&
    ($user_type == 'seller'
        // Ищем среди партнёров, если к ним применим хоть один фильтр
        || !$user_type && (empty($filter['subsystem']) || !empty($filter['email']) || !empty($filter['site']) || !empty($filter['src_id']) || !empty($filter['country']) ||
            !empty($filter['source']) || !empty($filter['campaign']) || !empty($filter['medium']) || !empty($filter['ad'])))
        ) {
    $UserListing = new \Da\User\Listing($Admin, $manager_id);

    // Установка фильтров поиска
    foreach ($filter as $f => $value) {
        $UserListing->setListFilter($f, $value);
    }

    // Общее кол-во продавцов в выборке
    $count = $UserListing->getPartnersCount();

    if ($user_type == 'seller') {
        // Поиск только по партнёрам - задействуем сортировку и постраничность
        $paginator  = new \Da\View\Paginator($page_items);

        $order = \Da\Std::grab('order');

        // Задаем кол-во партнёров в листалке
        $paginator->setAllItemsCnt($count);
        $current_page = $paginator->getCurrentPage();

        $pages_cnt = $paginator->getAllPagesCnt();
        $tpl->assign('pages_cnt', $pages_cnt);
        $tpl->assign('current_page', $current_page);
        $tpl->assign('order', $order);

        if ($pages_cnt > 1) {
            // Итоговые суммы для всей выборки
            $total  = $UserListing->getPartnersTotal();
            $tpl->assign('partners_total', $total);
        }
    }

    // Сами данные
    $data = $UserListing->getPartnersPage($current_page, $page_items, $order);

    $tpl->assign('partners_list', $data);
    $tpl->assign('partners_count', $count);
}

// Ищем среди обменщиков
if (!$is_first_show && (!$user_type || $user_type == 'exchanger')) {
    $UserListing = new \Da\User\Listing($Admin, $manager_id);

    // Установка фильтров поиска
    foreach ($filter as $f => $value) {
        $UserListing->setListFilter($f, $value, TRUE);
    }

    // Общее кол-во продавцов в выборке
    $count = $UserListing->getExchangersCount();

    if ($user_type == 'exchanger') {
        // Поиск только по обменщикам - задействуем сортировку и постраничность
        $paginator  = new \Da\View\Paginator($page_items);

        $order = \Da\Std::grab('order');

        // Задаем кол-во обменщиков в листалке
        $paginator->setAllItemsCnt($count);
        $current_page = $paginator->getCurrentPage();

        $pages_cnt = $paginator->getAllPagesCnt();
        $tpl->assign('pages_cnt', $pages_cnt);
        $tpl->assign('current_page', $current_page);
        $tpl->assign('order', $order);

        if ($pages_cnt > 1) {
            // Итоговые суммы для всей выборки
            $total  = $UserListing->getExchangersTotal();
            $tpl->assign('exchangers_total', $total);
        }
    }

    // Сами данные
    $data = $UserListing->getExchangersPage($current_page, $page_items, $order);

    $tpl->assign('exchangers_list', $data);
    $tpl->assign('exchangers_count', $count);
}

$tpl->assign('manager_filter', $manager_filter);
$tpl->assign('manager_id', $manager_id);
$tpl->assign('current_type', $user_type);
$tpl->assign('action', $action);
$tpl->assign('content', 'users');
