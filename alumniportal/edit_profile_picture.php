<div class="flex items-center space-x-4 mb-6">
    <div class="relative">
        <img id="profilePreview" src="<?= htmlspecialchars($user['profile_pic'] ?? 'default_profile.jpg') ?>" 
             class="w-24 h-24 rounded-full object-cover border-2 border-gray-300">
        <label for="profile_pic" class="absolute bottom-0 right-0 bg-blue-500 text-white p-1 rounded-full cursor-pointer">
            <i class="fas fa-camera text-xs"></i>
            <input type="file" id="profile_pic" name="profile_pic" accept="image/*" class="hidden" onchange="previewImage(this)">
        </label>
    </div>
    <div>
        <p class="font-medium">Profile Photo</p>
        <p class="text-sm text-gray-500">Recommended size: 150x150 px</p>
    </div>
</div>