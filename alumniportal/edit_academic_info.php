<div class="border-t pt-4 mb-6">
    <h4 class="font-bold mb-2">Academic Information</h4>
    
    <div class="mb-4">
        <label class="block text-gray-700 mb-1">Current Batch</label>
        <input type="text" value="<?= htmlspecialchars($user['batch_name'] ?? 'Not assigned') ?>" class="w-full px-3 py-2 border rounded-lg bg-gray-100" readonly>
    </div>
    
    <div class="mb-4">
        <label class="block text-gray-700 mb-1">Current Course</label>
        <input type="text" value="<?= htmlspecialchars($user['course_name'] ?? 'Not assigned') ?>" class="w-full px-3 py-2 border rounded-lg bg-gray-100" readonly>
    </div>
    
    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-yellow-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm text-yellow-700">
                    To change your batch or course, please submit a request below.
                </p>
            </div>
        </div>
    </div>
    
    <button type="button" onclick="openAcademicRequestModal()" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-1"></i> Request Academic Change
    </button>
</div>