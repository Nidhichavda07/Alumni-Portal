<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div>
        <label class="block text-gray-700 mb-1">Full Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" 
               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
    </div>
    <div>
        <label class="block text-gray-700 mb-1">Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" readonly>
    </div>
    <div>
        <label class="block text-gray-700 mb-1">Phone</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
    <div>
        <label class="block text-gray-700 mb-1">Date of Birth</label>
        <input type="date" name="dob" value="<?= htmlspecialchars($user['dob'] ?? '') ?>" 
               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>
</div>