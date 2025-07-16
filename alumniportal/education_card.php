<?php
// Education Card Component
$education = $db->prepare("
    SELECT * FROM education_details 
    WHERE user_id = ? 
    ORDER BY FIELD(education_level, 'phd', 'masters', 'bachelors', 'hsc', 'ssc')
")->execute([$profile_id])->fetchAll();
?>
<div class="bg-white rounded-lg shadow-md p-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">Education</h2>
        <?php if ($is_own_profile): ?>
            <button onclick="openEducationModal()" class="text-blue-600 hover:text-blue-800 text-sm">
                <i class="fas fa-plus mr-1"></i> Add
            </button>
        <?php endif; ?>
    </div>
    
    <?php if (empty($education)): ?>
        <p class="text-gray-500">No education information added</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($education as $edu): ?>
                <div class="border-l-2 border-green-500 pl-4">
                    <h3 class="font-bold">
                        <?= htmlspecialchars($edu['degree_name'] ?? ucfirst($edu['education_level'])) ?>
                        <?= !empty($edu['specialization']) ? ' in ' . htmlspecialchars($edu['specialization']) : '' ?>
                    </h3>
                    <p class="text-gray-600"><?= htmlspecialchars($edu['university']) ?></p>
                    <p class="text-sm text-gray-500">
                        <?= $edu['year_of_passing'] ? 'Graduated ' . $edu['year_of_passing'] : '' ?>
                        <?= !empty($edu['percentage']) ? ' (' . $edu['percentage'] . '%)' : '' ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>