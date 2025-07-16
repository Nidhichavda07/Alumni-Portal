<div class="border-t pt-4 mb-6">
    <h4 class="font-bold mb-2">Privacy Settings</h4>
    <div class="space-y-4">
        <div>
            <label class="flex items-center justify-between">
                <span class="font-medium">Email Visibility</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="show_email" <?= ($user['show_email'] ?? false) ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </label>
            <p class="text-sm text-gray-500 mt-1">When enabled, your email will be visible to other users.</p>
        </div>
        
        <div>
            <label class="flex items-center justify-between">
                <span class="font-medium">Phone Visibility</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="show_phone" <?= ($user['show_phone'] ?? false) ? 'checked' : '' ?> class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </label>
            <p class="text-sm text-gray-500 mt-1">When enabled, your phone number will be visible to other users.</p>
        </div>
    </div>
</div>