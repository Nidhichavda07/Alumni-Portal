<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div>
        <label class="block text-gray-700 mb-1">City</label>
        <input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>" 
               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
    <div>
        <label class="block text-gray-700 mb-1">State</label>
        <input type="text" name="state" value="<?= htmlspecialchars($user['state'] ?? '') ?>" 
               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
    <div>
        <label class="block text-gray-700 mb-1">Pincode</label>
        <input type="text" name="pincode" value="<?= htmlspecialchars($user['pincode'] ?? '') ?>" 
               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
</div>