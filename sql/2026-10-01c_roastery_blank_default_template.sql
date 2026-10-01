-- Make the default label template a reusable, genuinely empty canvas.
UPDATE `coffee_packaging_label_template`
SET `template_name` = 'Blank Canvas',
    `description` = 'Kanvas kosong 90 x 140 mm. Tambahkan elemen sesuai kebutuhan.',
    `design_json` = '{"schema":"roastery-label-template-v1","canvas":{"width":90,"height":140,"theme":"heritage-cream","artworkMode":"full","artworkFit":"stretch","patternMode":"none","blankCanvas":true},"print":{"paper":"A4","orientation":"portrait","paperW":210,"paperH":297,"perSheet":4,"margin":6,"gap":3,"cutLine":true},"elements":[],"blocks":{}}',
    `is_system` = 1,
    `is_active` = 1,
    `updated_at` = CURRENT_TIMESTAMP
WHERE `template_key` = 'classic-portrait';
