<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

trait CreatesTessuSchema
{
    protected function createTessuSchema(): void
    {
        // Rimuoviamo RefreshDatabase che fallisce su SQLite per raw alter queries
        // Creiamo manualmente le tabelle necessarie per questi test.
        
        Schema::create('component_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });

        Schema::create('fabrics', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable();
            $table->string('code')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('fabric_id')->nullable();
            $table->foreignId('color_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('component_id')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->boolean('is_variable')->default(false);
            $table->string('variable_slot')->nullable();
            $table->timestamps();
        });

        Schema::create('order_product_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id');
            $table->foreignId('fabric_id')->nullable();
            $table->foreignId('color_id')->nullable();
            $table->foreignId('resolved_component_id')->nullable();
            $table->text('color_notes')->nullable();
            $table->decimal('surcharge_fixed_applied', 10, 2)->default(0);
            $table->decimal('surcharge_percent_applied', 5, 2)->default(0);
            $table->decimal('surcharge_total_applied', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('product_fabrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('fabric_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('surcharge_type')->nullable();
            $table->decimal('surcharge_value', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('product_colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('color_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('surcharge_type')->nullable();
            $table->decimal('surcharge_value', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable();
            $table->timestamp('order_date')->nullable();
            $table->timestamp('delivery_date')->nullable();
            $table->string('status')->default('new');
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->string('status')->default('new');
            $table->timestamps();
        });

        Schema::create('order_item_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->nullable();
            $table->foreignId('fabric_id')->nullable();
            $table->foreignId('color_id')->nullable();
            $table->foreignId('resolved_component_id')->nullable();
            $table->timestamps();
        });
        
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->nullable();
            $table->string('type')->nullable();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->string('reference')->nullable();
            $table->timestamps();
        });
        
        Schema::create('product_stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->nullable();
            $table->decimal('available', 10, 2)->default(0);
            $table->decimal('reserved', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description')->nullable();
            $table->string('subject_type')->nullable();
            $table->string('event')->nullable();
            $table->foreignId('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->foreignId('causer_id')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }
}
