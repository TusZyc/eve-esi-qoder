@extends('layouts.app')

@section('title', '测试页面')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white/10 backdrop-blur-lg rounded-xl p-6">
        <h2 class="text-2xl font-bold mb-4">環境變數測試</h2>
        
        <div class="space-y-4">
            <div class="p-4 bg-white/5 rounded-lg">
                <h3 class="font-bold text-lg mb-2">$isLoggedIn 變數測試</h3>
                <p class="text-white/80">變數類型: {{ gettype($isLoggedIn ?? '未設定') }}</p>
                <p class="text-white/80">變數值: {{ var_export($isLoggedIn ?? null, true) }}</p>
                <p class="text-white/80">是否為 true: {{ $isLoggedIn ?? false ? '是' : '否' }}</p>
                <p class="text-white/80">布林判斷結果: {{ $isLoggedIn ?? false ? '顯示已授權按鈕' : '顯示未授權按鈕' }}</p>
            </div>
            
            <div class="p-4 bg-white/5 rounded-lg">
                <h3 class="font-bold text-lg mb-2">$user 變數測試</h3>
                <p class="text-white/80">變數類型: {{ gettype($user ?? '未設定') }}</p>
                <p class="text-white/80">變數值: {{ $user ? '已設定' : 'null' }}</p>
                @if($user)
                    <p class="text-white/80">使用者名稱: {{ $user->name }}</p>
                    <p class="text-white/80">角色ID: {{ $user->eve_character_id ?? '無' }}</p>
                @endif
            </div>
            
            <div class="p-4 bg-white/5 rounded-lg">
                <h3 class="font-bold text-lg mb-2">導航欄測試</h3>
                <p class="text-white/80 mb-2">請檢查上方導航欄：</p>
                <ul class="list-disc list-inside text-white/60">
                    <li>儀表盤按鈕應該連結到: {{ $isLoggedIn ?? false ? '/dashboard' : '/guest' }}</li>
                    <li>知識選單按鈕應該: {{ $isLoggedIn ?? false ? '顯示' : '隱藏' }}</li>
                    <li>資產按鈕應該: {{ $isLoggedIn ?? false ? '顯示' : '隱藏' }}</li>
                    <li>角色管理按鈕應該: {{ $isLoggedIn ?? false ? '顯示' : '隱藏' }}</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
