<?php

use App\Enums\FormFilterType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_filters', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index()->comment('过滤类型');
            $table->string('value', 255)->comment('过滤内容');
            $table->timestamps();
            $table->unique(['type', 'value']);
            $table->comment('表单过滤项表');
        });

        $this->seedFromOpenapiConfig();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_filters');
    }

    private function seedFromOpenapiConfig(): void
    {
        $rows = [];
        $now = date('Y-m-d H:i:s');

        $filterNames = config('openapi.filter_words', config('openapi.filter_keywords', []));
        if (is_array($filterNames)) {
            foreach ($filterNames as $name) {
                $name = trim((string)$name);

                if ($name !== '') {
                    $rows[] = [
                        'type' => FormFilterType::NAME->value,
                        'value' => $name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $filterPhones = config('openapi.filter_phone', []);
        if (is_array($filterPhones)) {
            foreach ($filterPhones as $phone) {
                $phone = trim((string)$phone);

                if ($phone !== '') {
                    $rows[] = [
                        'type' => FormFilterType::PHONE->value,
                        'value' => $phone,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (!empty($rows)) {
            DB::table('form_filters')->insertOrIgnore($rows);
        }
    }
};
