<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gpa_files', function (Blueprint $table) {
            $table->id('file_id')->comment('文件ID');
            $table->char('file_name')->comment('文件名');
            $table->char('origin_file_name')->comment('原始文件名');
            $table->char('file_path')->unique()->comment('文件目录');
            $table->unsignedInteger('file_size')->comment('文件大小（以字节为单位）');
            $table->char('mime_type')->comment('文件的MIME类型');
            $table->char('file_ext_name')->comment('文件扩展名');
            $table->unsignedBigInteger('account_id')->default(0)->comment('账号ID（上传人）');
            $table->string('checksum', 64)->comment('文件的校验和（SHA-256哈希值）');
            $table->timestamps();
            $table->softDeletes();

            $table->comment('文件表');
        });

        Schema::create('gpa_file_images', function (Blueprint $table) {
            $table->id('image_id')->comment('图片ID');
            $table->unsignedBigInteger('file_id')->comment('文件ID');
            $table->unsignedInteger('image_width')->comment('图片宽度（px）');
            $table->unsignedInteger('image_height')->comment('图片高度（px）');
            $table->timestamps();

            // 设置级联删除
            $table->foreign('file_id')
                ->references('file_id')
                ->on('gpa_files')
                ->onDelete('cascade');

            $table->comment('文件-附属信息-图片表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gpa_files');
        Schema::dropIfExists('gpa_file_images');
    }
};
