<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\CustomerRefundController;
use App\Http\Controllers\AdminRefundController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StaffOrderController;
use App\Http\Controllers\AdminAuditController;
use App\Http\Controllers\AdminInventoryController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\AdminReviewController;
use App\Http\Controllers\ProductReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'home'])->name('home');
Route::get('/shop', [StorefrontController::class, 'products'])->name('products.index');
Route::get('/shop/{product:slug}', [StorefrontController::class, 'show'])->name('products.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/{product}', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{product}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/repair', [RepairController::class, 'create'])->name('repair.create');
    Route::post('/repair', [RepairController::class, 'store'])->name('repair.store');

    Route::get('/account', [AccountController::class, 'index'])->name('account.index');
    Route::get('/account/profile', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('/account/profile', [AccountController::class, 'update'])->name('account.update');
    Route::get('/account/addresses', [AddressController::class, 'index'])->name('account.addresses');
    Route::get('/account/addresses/new', [AddressController::class, 'create'])->name('account.addresses.create');
    Route::post('/account/addresses', [AddressController::class, 'store'])->name('account.addresses.store');
    Route::get('/account/addresses/{address}/edit', [AddressController::class, 'edit'])->name('account.addresses.edit');
    Route::patch('/account/addresses/{address}', [AddressController::class, 'update'])->name('account.addresses.update');
    Route::patch('/account/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('account.addresses.default');
    Route::delete('/account/addresses/{address}', [AddressController::class, 'destroy'])->name('account.addresses.destroy');
    Route::get('/account/purchases', [AccountController::class, 'purchases'])->name('account.purchases');
    Route::post('/shop/{product:slug}/reviews', [ProductReviewController::class, 'store'])->name('reviews.store');
    Route::get('/account/reviews/{review}/edit', [ProductReviewController::class, 'edit'])->name('reviews.edit');
    Route::patch('/account/reviews/{review}', [ProductReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/account/reviews/{review}', [ProductReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::get('/account/orders/{order}', [CustomerOrderController::class, 'show'])->name('account.orders.show');
    Route::post('/account/orders/{order}/refunds', [CustomerRefundController::class, 'store'])->name('account.refunds.store');
    Route::get('/account/refunds', [CustomerRefundController::class, 'index'])->name('account.refunds');
    Route::get('/account/notifications', [NotificationController::class, 'index'])->name('account.notifications');
    Route::post('/account/notifications/read-all', [NotificationController::class, 'readAll'])->name('account.notifications.read-all');
    Route::post('/account/notifications/{notification}/read', [NotificationController::class, 'read'])->name('account.notifications.read');
    Route::post('/account/orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])->name('account.orders.cancel');
    Route::post('/account/orders/{order}/payment-reference', [CustomerOrderController::class, 'submitPayment'])->name('account.orders.payment-reference');
    Route::get('/account/repairs', [AccountController::class, 'repairs'])->name('account.repairs');
    Route::get('/account/wishlist', [AccountController::class, 'wishlist'])->name('account.wishlist');
    Route::post('/wishlist/{product}', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::get('/local-admin', [AdminController::class, 'index'])->middleware('role:admin')->name('admin.dashboard');
    Route::get('/local-admin/products', [AdminProductController::class, 'index'])->middleware('role:admin')->name('admin.products.index');
    Route::get('/local-admin/products/new', [AdminProductController::class, 'create'])->middleware('role:admin')->name('admin.products.create');
    Route::post('/local-admin/products', [AdminProductController::class, 'store'])->middleware('role:admin')->name('admin.products.store');
    Route::get('/local-admin/products/{product}/edit', [AdminProductController::class, 'edit'])->middleware('role:admin')->name('admin.products.edit');
    Route::patch('/local-admin/products/{product}', [AdminProductController::class, 'update'])->middleware('role:admin')->name('admin.products.update');
    Route::get('/local-admin/reviews', [AdminReviewController::class, 'index'])->middleware('role:admin')->name('admin.reviews.index');
    Route::patch('/local-admin/reviews/{review}', [AdminReviewController::class, 'moderate'])->middleware('role:admin')->name('admin.reviews.moderate');
    Route::patch('/local-admin/orders/{order}', [AdminController::class, 'updateOrder'])->middleware('role:admin')->name('admin.orders.update');
    Route::get('/local-admin/orders/{order}', [StaffOrderController::class, 'show'])->middleware('role:admin')->name('admin.orders.show');
    Route::patch('/local-admin/orders/{order}/tracking', [StaffOrderController::class, 'updateTracking'])->middleware('role:admin')->name('admin.orders.tracking');
    Route::patch('/local-admin/products/{product}/stock', [AdminController::class, 'updateStock'])->middleware('role:admin')->name('admin.products.stock');
    Route::get('/local-admin/products/{product}/history', [AdminInventoryController::class, 'show'])->middleware('role:admin')->name('admin.products.history');
    Route::get('/local-admin/audit', [AdminAuditController::class, 'index'])->middleware('role:admin')->name('admin.audit');
    Route::get('/local-admin/refunds', [AdminRefundController::class, 'index'])->middleware('role:admin')->name('admin.refunds.index');
    Route::patch('/local-admin/refunds/{refund}', [AdminRefundController::class, 'review'])->middleware('role:admin')->name('admin.refunds.review');
    Route::post('/local-admin/refunds/{refund}/complete', [AdminRefundController::class, 'complete'])->middleware('role:admin')->name('admin.refunds.complete');
    Route::post('/local-admin/orders/{order}/confirm-payment', [AdminController::class, 'confirmPayment'])->middleware('role:admin')->name('admin.orders.confirm-payment');
    Route::post('/local-admin/orders/{order}/reject-payment', [AdminController::class, 'rejectPayment'])->middleware('role:admin')->name('admin.orders.reject-payment');
    Route::patch('/local-admin/repairs/{repair}', [AdminController::class, 'updateRepair'])->middleware('role:admin')->name('admin.repairs.update');
});

Route::view('/image-credits', 'image-credits')->name('image-credits');
