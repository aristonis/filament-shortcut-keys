<?php

use Aristonis\FilamentShortcutKeys\Filament\NavigationOrder;
use Aristonis\FilamentShortcutKeys\Tests\Support\Models\User;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\UserResource\Pages\ListUsers;
use Filament\Clusters\Cluster;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Page;
use Filament\Resources\Resource;

it('orders ungrouped items first, then each group in registered order, sorting inside a group', function () {
    $ordered = (new NavigationOrder)->order(
        [NavOrderBlogPosts::class, NavOrderShopProducts::class, NavOrderShopOrders::class, NavOrderHome::class],
        ['Shop', 'Blog'],
        includeHidden: false,
    );

    expect($ordered)->toBe([NavOrderHome::class, NavOrderShopOrders::class, NavOrderShopProducts::class, NavOrderBlogPosts::class]);
});

it('treats a missing sort as -1 and keeps input order between equal sorts, as the sidebar does', function () {
    $ordered = (new NavigationOrder)->order(
        [NavOrderSortedZero::class, NavOrderUnsortedB::class, NavOrderSortedMinusTwo::class, NavOrderUnsortedA::class],
        [],
        includeHidden: false,
    );

    expect($ordered)->toBe([NavOrderSortedMinusTwo::class, NavOrderUnsortedB::class, NavOrderUnsortedA::class, NavOrderSortedZero::class]);
});

it('matches registered NavigationGroup objects by key or by label', function () {
    $ordered = (new NavigationOrder)->order(
        [NavOrderShopProducts::class, NavOrderBlogPosts::class],
        ['blog' => NavigationGroup::make('Blog'), 'store' => NavigationGroup::make('Shop')],
        includeHidden: false,
    );

    expect($ordered)->toBe([NavOrderBlogPosts::class, NavOrderShopProducts::class]);
});

it('ranks groups registered under string keys by their position', function () {
    $ordered = (new NavigationOrder)->order(
        [NavOrderBlogPosts::class, NavOrderShopProducts::class],
        ['store' => 'Shop', 'news' => 'Blog'],
        includeHidden: false,
    );

    expect($ordered)->toBe([NavOrderShopProducts::class, NavOrderBlogPosts::class]);
});

it('ranks enum groups by the order of their cases', function () {
    $ordered = (new NavigationOrder)->order(
        [NavOrderSalesReport::class, NavOrderCatalogItems::class],
        [],
        includeHidden: false,
    );

    expect($ordered)->toBe([NavOrderCatalogItems::class, NavOrderSalesReport::class]);
});

it('puts groups that are not registered after the registered ones', function () {
    $ordered = (new NavigationOrder)->order(
        [NavOrderBlogPosts::class, NavOrderShopOrders::class, NavOrderShopProducts::class],
        ['Shop'],
        includeHidden: false,
    );

    expect($ordered)->toBe([NavOrderShopOrders::class, NavOrderShopProducts::class, NavOrderBlogPosts::class]);
});

it('keeps each unregistered group together, in order of its first item', function () {
    $ordered = (new NavigationOrder)->order(
        [NavOrderShopOrders::class, NavOrderBlogArchive::class, NavOrderShopProducts::class],
        [],
        includeHidden: false,
    );

    expect($ordered)->toBe([NavOrderShopOrders::class, NavOrderShopProducts::class, NavOrderBlogArchive::class]);
});

it('leaves out pages and resources hidden from the sidebar by default', function () {
    $ordered = (new NavigationOrder)->order(
        [NavOrderHiddenPage::class, NavOrderHome::class, NavOrderHiddenResource::class],
        [],
        includeHidden: false,
    );

    expect($ordered)->toBe([NavOrderHome::class]);
});

it('letters hidden targets after every visible item when asked to include them', function () {
    $ordered = (new NavigationOrder)->order(
        [NavOrderHiddenPage::class, NavOrderBlogPosts::class, NavOrderHome::class],
        ['Blog'],
        includeHidden: true,
    );

    expect($ordered)->toBe([NavOrderHome::class, NavOrderBlogPosts::class, NavOrderHiddenPage::class]);
});

it('letters cluster members after every main sidebar item, since they live in the cluster sub-navigation', function () {
    $ordered = (new NavigationOrder)->order(
        [NavOrderClusterPage::class, NavOrderHome::class],
        [],
        includeHidden: false,
    );

    expect($ordered)->toBe([NavOrderHome::class, NavOrderClusterPage::class]);
});

it('leaves out resources Filament never links to: nested children and those without an index page', function () {
    $ordered = (new NavigationOrder)->order(
        [NavOrderChildResource::class, NavOrderIndexlessResource::class, NavOrderParentResource::class],
        [],
        includeHidden: true,
    );

    expect($ordered)->toBe([NavOrderParentResource::class]);
});

it('includes a hidden target the overlay pins a letter to, without including the others', function () {
    $ordered = (new NavigationOrder)->order(
        [NavOrderHiddenPage::class, NavOrderOtherHiddenPage::class, NavOrderHome::class],
        [],
        includeHidden: false,
        pinned: [NavOrderHiddenPage::class],
    );

    expect($ordered)->toBe([NavOrderHome::class, NavOrderHiddenPage::class]);
});

enum NavOrderStoreGroup
{
    case Catalog;
    case Sales;
}

final class NavOrderHome extends Page
{
    protected static ?int $navigationSort = 5;
}

final class NavOrderShopProducts extends Page
{
    protected static string | UnitEnum | null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 2;
}

final class NavOrderShopOrders extends Page
{
    protected static string | UnitEnum | null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 1;
}

final class NavOrderBlogPosts extends Page
{
    protected static string | UnitEnum | null $navigationGroup = 'Blog';
}

final class NavOrderBlogArchive extends Page
{
    protected static string | UnitEnum | null $navigationGroup = 'Blog';

    protected static ?int $navigationSort = 1;
}

final class NavOrderSortedZero extends Page
{
    protected static ?int $navigationSort = 0;
}

final class NavOrderSortedMinusTwo extends Page
{
    protected static ?int $navigationSort = -2;
}

final class NavOrderUnsortedA extends Page {}

final class NavOrderUnsortedB extends Page {}

final class NavOrderSalesReport extends Page
{
    protected static string | UnitEnum | null $navigationGroup = NavOrderStoreGroup::Sales;
}

final class NavOrderCatalogItems extends Page
{
    protected static string | UnitEnum | null $navigationGroup = NavOrderStoreGroup::Catalog;
}

final class NavOrderHiddenPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;
}

final class NavOrderHiddenResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;
}

final class NavOrderOtherHiddenPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;
}

final class NavOrderCluster extends Cluster {}

final class NavOrderClusterPage extends Page
{
    protected static ?string $cluster = NavOrderCluster::class;

    protected static ?int $navigationSort = -5;
}

final class NavOrderParentResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/')];
    }
}

final class NavOrderChildResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $parentResource = NavOrderParentResource::class;

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/')];
    }
}

final class NavOrderIndexlessResource extends Resource
{
    protected static ?string $model = User::class;
}
