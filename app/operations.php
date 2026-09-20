<?php
// goa/app/operations.php - Operations page with Quasar UMD (Vue 3 + Quasar 2)
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('operations.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">

<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <style>
        .operations-table {
            height: calc(100vh - 180px);
        }

        /* Headers left-aligned and wrapping, whatever the column's align says;
           same treatment as the crops.php table. */
        .operations-table thead th {
            text-align: left;
            white-space: normal;
        }

        /* Sort marker pinned to the right edge instead of inline beside the
           label. Offset with a margin rather than a transform: the descending
           state rotates the icon and would drop a translate. The padding is
           the space it no longer takes up. */
        .operations-table thead th.sortable {
            position: relative;
            padding-right: 26px;
        }
        .operations-table thead th .q-table__sort-icon {
            position: absolute;
            top: 50%;
            right: 6px;
            margin: -0.6em 0 0;
        }

        .camera-capture-input {
            display: none;
        }

        .scouting-image-placeholder {
            width: 72px;
            height: 72px;
            border-radius: 6px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dialog-full-height .q-dialog__bg {
            max-height: 95vh;
        }

        @media (max-width: 599px) {
            .operations-table {
                height: calc(100vh - 160px);
            }
        }
    </style>
</head>

<body>
    <div id="q-app">
        <q-layout view="hHh lpR fFf">
            <!-- Header -->
            <q-header class="ag-base-q-header">
                <q-toolbar>
                    <q-space></q-space>
                    <q-toolbar-title shrink>
                        <q-icon name="local_florist" size="md" class="q-mr-sm"></q-icon>
                        <?php echo th('operations.toolbar'); ?>
                    </q-toolbar-title>
                    <q-space></q-space>
                </q-toolbar>
            </q-header>

            <!-- Page Content -->
            <q-page-container>
                <q-page class="q-pa-md">

                    <!-- Linear Progress -->
                    <q-linear-progress v-if="loading" color="primary" class="q-mb-md" indeterminate></q-linear-progress>

                    <!-- q-table -->
                    <q-table class="operations-table full-width" flat
                        :rows="cropsOperations" :columns="columns" row-key="id"
                        :filter="filter" :loading="loading" separator="cell" virtual-scroll
                        hide-pagination :rows-per-page-options="[0]"
                        style="height: calc(100vh - 100px);"
                        @row-click="handleRowClick"
                        :no-data-label="<?php echo tv('operations.no_data'); ?>"
                        :no-results-label="<?php echo tv('operations.no_results'); ?>">
                        <template v-slot:top-left>
                            <div class="row items-center q-gutter-sm no-wrap">
                                <q-btn color="primary" icon="add" label="<?php echo th('operations.add'); ?>" @click="openForm" style="width: 250px;"></q-btn>
                            </div>
                        </template>
                        <template v-slot:top-right>
                            <div class="row items-center q-gutter-sm no-wrap">
                                <q-input outlined dense debounce="300" v-model="filter" placeholder="<?php echo th('common.search'); ?>" style="width: 50%;">
                                    <template v-slot:append>
                                        <q-btn v-if="filter" flat round dense icon="close" @click.stop="filter = ''"></q-btn>
                                        <q-icon name="search"></q-icon>
                                    </template>
                                </q-input>
                                <q-btn flat round dense icon="refresh" @click="fetchCropsOperations" :loading="loading"></q-btn>
                            </div>
                        </template>
                        <template v-slot:body-cell-cultura="props">
                            <q-td :props="props">
                                <q-badge v-if="props.row.animal_id" color="brown">{{ props.row.animal_name }}</q-badge>
                                <q-badge v-else color="green">{{ props.row.production_name }}</q-badge>
                            </q-td>
                        </template>
                        <template v-slot:body-cell-talhao="props">
                            <q-td :props="props">{{ props.row.field_name }}</q-td>
                        </template>
                        <template v-slot:body-cell-operacao="props">
                            <q-td :props="props">{{ props.row.operation_type_name }}</q-td>
                        </template>
                        <template v-slot:body-cell-data="props">
                            <q-td :props="props" class="text-primary">{{ props.row.operation_date }}</q-td>
                        </template>
                    </q-table>
                </q-page>
            </q-page-container>
        </q-layout>

        <!-- Edit/Add Form Dialog -->
        <q-dialog v-model="showForm" persistent maximized>
            <q-card class="form-card" style="width: 100%; max-width: 100vw;">
                <q-card-section class="modal-header row items-center">
                    <div class="text-h6">{{ selectedItem?.id ? <?php echo tj('operations.edit_title'); ?> : <?php echo tj('operations.add_title'); ?> }}</div>
                    <q-space></q-space>
                    <q-btn icon="close" flat round dense @click="closeForm"></q-btn>
                </q-card-section>
                <q-separator></q-separator>

                <q-card-section class="q-pt-md form-scroll">
                    <!-- Assistant draft banner. Nothing is saved until the user
                         presses Save below, so this states what was pre-filled and
                         what the assistant could not work out. -->
                    <q-banner v-if="proposal" dense rounded class="q-mb-md"
                        :class="proposal.complete ? 'bg-blue-1 text-blue-10' : 'bg-orange-1 text-orange-10'">
                        <template v-slot:avatar>
                            <q-icon :name="proposal.complete ? 'auto_awesome' : 'warning'"></q-icon>
                        </template>
                        <div class="text-weight-500">
                            {{ proposal.complete
                                ? <?php echo tj('operations.proposal_ready'); ?>
                                : <?php echo tj('operations.proposal_partial'); ?> }}
                        </div>
                        <ul v-if="proposal.unresolved.length" class="q-my-xs q-pl-md">
                            <li v-for="(u, i) in proposal.unresolved" :key="i">{{ u }}</li>
                        </ul>
                        <template v-slot:action>
                            <q-btn flat dense :label="<?php echo tj('operations.proposal_dismiss'); ?>" @click="proposal = null"></q-btn>
                        </template>
                    </q-banner>

                    <form @submit.prevent="handleSubmit">
                        <!-- Operation Date -->
                        <div class="q-mb-md">
                            <label class="q-mb-xs block text-weight-500"><?php echo th('operations.date'); ?></label>
                            <q-input v-model="form.operation_date" mask="####-##-##" outlined dense
                                placeholder="YYYY-MM-DD" :rules="[
                                    val => {
                                        if (!val) return <?php echo tv('common.required_field'); ?>;
                                        const [y, m, d] = val.split('-').map(Number);
                                        const date = new Date(y, m - 1, d);
                                        const isValid = date.getFullYear() === y && date.getMonth() === m - 1 && date.getDate() === d;
                                        return isValid || <?php echo tv('operations.invalid_date'); ?>;
                                    }
                                ]">
                                <template v-slot:append>
                                    <q-icon name="event" class="cursor-pointer">
                                        <q-popup-proxy ref="dateProxy" cover transition-show="scale" transition-hide="scale">
                                            <q-date v-model="form.operation_date" mask="YYYY-MM-DD"
                                                @update:model-value="$refs.dateProxy.hide()">
                                            </q-date>
                                        </q-popup-proxy>
                                    </q-icon>
                                </template>
                            </q-input>
                            <!-- Advisory. The save is never blocked by this. -->
                            <q-banner v-if="dateWarnings.length" dense rounded class="q-mt-sm bg-orange-1 text-orange-10">
                                <template v-slot:avatar>
                                    <q-icon name="warning"></q-icon>
                                </template>
                                <div class="text-weight-500"><?php echo th('operations.date_warning'); ?></div>
                                <ul class="q-my-xs q-pl-md">
                                    <li v-for="(w, i) in dateWarnings" :key="i">{{ w }}</li>
                                </ul>
                            </q-banner>
                        </div>

                        <!-- Operation Selection -->
                        <div class="q-mb-md">
                            <label class="q-mb-xs block text-weight-500"><?php echo th('operations.operation_type'); ?></label>
                            <q-select ref="operationTypeSelect" v-model="form.operation_type_id" :options="filteredOperationTypes"
                                option-value="id" option-label="name" outlined dense emit-value map-options
                                :disable="isEditing"
                                use-input fill-input hide-selected @blur="validateField('operation_type_id')"
                                @filter="filterOperationTypes" :error="!!errors.operation_type_id"
                                :error-message="errors.operation_type_id" :rules="[val => !!val || <?php echo tv('operations.select_operation_type'); ?>]">
                            </q-select>
                        </div>

                        <!-- Target pane. The operation type's tpr picks which one. -->
                        <div v-if="selectedTpr === 'v' || selectedTpr === 'i' || selectedTpr === 'h'" class="q-mb-md">
                            <label class="q-mb-xs block text-weight-500">
                                {{ isEditing ? <?php echo tj('operations.crop'); ?> : <?php echo tj('operations.crops'); ?> }}
                            </label>
                            <q-select v-if="isEditing" ref="cropSelect" v-model="form.crop_id" :options="filteredCrops"
                                option-value="crop_id" :option-label="cropLabel" outlined dense emit-value map-options
                                use-input fill-input hide-selected @blur="validateField('crop_id')"
                                @filter="filterCrops" :error="!!errors.crop_id" :error-message="errors.crop_id"
                                :rules="[val => !!val || <?php echo tv('operations.select_crop'); ?>]">
                            </q-select>
                            <q-select v-else ref="cropSelect" v-model="form.crop_ids" :options="filteredCrops"
                                option-value="crop_id" :option-label="cropLabel" outlined dense emit-value map-options
                                multiple use-chips use-input @filter="filterCrops"
                                :error="!!errors.crop_ids" :error-message="errors.crop_ids"
                                :max-values="selectedTpr === 'h' ? 1 : undefined"
                                :rules="[val => val && val.length > 0 || <?php echo tv('operations.select_crop_min'); ?>]">
                            </q-select>
                        </div>

                        <div v-else-if="selectedTpr === 'a'" class="q-mb-md">
                            <label class="q-mb-xs block text-weight-500">
                                {{ isEditing ? <?php echo tj('operations.animal'); ?> : <?php echo tj('operations.animals'); ?> }}
                            </label>
                            <q-select v-if="isEditing" ref="animalSelect" v-model="form.animal_id" :options="filteredAnimals"
                                option-value="id" :option-label="animalLabel" outlined dense emit-value map-options
                                use-input fill-input hide-selected @blur="validateField('animal_id')"
                                @filter="filterAnimals" :error="!!errors.animal_id" :error-message="errors.animal_id"
                                :rules="[val => !!val || <?php echo tv('operations.select_animal'); ?>]">
                            </q-select>
                            <q-select v-else ref="animalSelect" v-model="form.animal_ids" :options="filteredAnimals"
                                option-value="id" :option-label="animalLabel" outlined dense emit-value map-options
                                multiple use-chips use-input @filter="filterAnimals"
                                :error="!!errors.animal_ids" :error-message="errors.animal_ids"
                                :rules="[val => val && val.length > 0 || <?php echo tv('operations.select_animal_min'); ?>]">
                            </q-select>
                        </div>

                        <!-- Irrigation (tpr 'i'): also stored in senmir via senmir.cop. -->
                        <div v-if="selectedTpr === 'i'" class="q-mb-md">
                            <label class="q-mb-xs block text-weight-500"><?php echo th('operations.irrigation_mm'); ?></label>
                            <q-input v-model="form.irrigation_mm" outlined dense type="text" inputmode="decimal"
                                :error="!!errors.irrigation_mm" :error-message="errors.irrigation_mm">
                                <template v-slot:append>
                                    <span class="text-caption text-grey-7">mm</span>
                                </template>
                            </q-input>
                        </div>

                        <!-- Harvest (tpr 'h'): stored in operations_output via operations_output.cop. -->
                        <div v-if="selectedTpr === 'h'" class="q-mb-md">
                            <label class="q-mb-xs block text-weight-500"><?php echo th('operations.harvest_qty'); ?></label>
                            <q-input v-model="form.harvest_qty" outlined dense type="text" inputmode="decimal"
                                :error="!!errors.harvest_qty" :error-message="errors.harvest_qty">
                                <template v-slot:append>
                                    <span class="text-caption text-grey-7">{{ harvestUnit }}</span>
                                </template>
                            </q-input>
                        </div>

                        <!-- Operation Images. Nothing below the type select can be
                             filled in before the tpr is known. -->
                        <div v-if="selectedTpr" class="q-mb-md">
                            <label class="q-mb-xs block text-weight-500"><?php echo th('operations.images'); ?></label>
                            <div class="row items-center q-gutter-sm q-mt-sm">
                                <q-btn color="primary" icon="add" label="<?php echo th('operations.add_image'); ?>" @click="addEmptyScoutingImageRow"></q-btn>
                                <q-btn icon="photo_camera" label="<?php echo th('operations.open_camera'); ?>" @click="openCameraCapture"></q-btn>
                                <div class="text-caption text-grey-7"><?php echo th('operations.camera_hint'); ?></div>
                            </div>
                            <input ref="cameraInputRef" class="camera-capture-input" type="file" accept="image/*" capture="environment" @change="handleCameraCapture">

                            <div v-if="scoutingImageRows.length === 0" class="text-grey q-mt-sm"><?php echo th('operations.no_image'); ?></div>

                            <q-list v-if="scoutingImageRows.length > 0" bordered separator class="q-mt-md">
                                <q-item v-for="(imageRow, index) in scoutingImageRows" :key="imageRow.key" class="q-pa-sm">
                                    <q-item-section avatar top>
                                        <q-img v-if="imageRow.previewUrl" :src="imageRow.previewUrl"
                                            style="width: 72px; height: 72px; border-radius: 6px;" no-spinner>
                                        </q-img>
                                        <div v-else class="scouting-image-placeholder"><q-icon name="image" size="24px" color="grey-6"></q-icon></div>
                                    </q-item-section>
                                    <q-item-section>
                                        <div class="row q-col-gutter-sm">
                                            <div class="col-12 col-md-6">
                                                <q-file :model-value="imageRow.file" outlined dense clearable accept="image/*"
                                                    :label="imageRow.id ? <?php echo tv('operations.replace_image'); ?> : <?php echo tv('operations.select_image'); ?>"
                                                    @update:model-value="value => updateScoutingImageRowFile(index, value)">
                                                </q-file>
                                            </div>
                                            <div class="col-12 col-md-6">
                                                <q-input v-model="imageRow.note" type="textarea" outlined dense label="<?php echo th('operations.note'); ?>">
                                                </q-input>
                                            </div>
                                        </div>
                                        <div class="text-caption text-grey-7 ellipsis q-mt-sm">{{ imageRow.fileName || <?php echo tj('operations.unknown_file'); ?> }}</div>
                                    </q-item-section>
                                    <q-item-section side top>
                                        <q-btn flat dense round icon="delete" size="sm" @click="removeScoutingImageRow(index)">
                                        </q-btn>
                                    </q-item-section>
                                </q-item>
                            </q-list>
                        </div>

                        <!-- Inputs Section -->
                        <div v-if="selectedTpr" class="q-mb-md">
                            <div class="text-weight-500 q-mb-md"><?php echo th('operations.inputs'); ?></div>
                            <div v-if="form.inputs.length === 0" class="text-grey q-mb-md"><?php echo th('operations.no_input'); ?></div>
                            <q-list v-else bordered separator class="q-mb-md">
                                <q-item v-for="(input, index) in form.inputs" :key="index" class="q-pa-md">
                                    <q-item-section>
                                        <div class="row q-col-gutter-sm items-center">
                                            <div class="col-12 col-md-7">
                                                <q-select v-model="input.input_id" :options="getAvailableInputsForRow(index)"
                                                    option-value="id" option-label="name" outlined dense emit-value map-options
                                                    label="<?php echo th('operations.select_input'); ?>">
                                                </q-select>
                                            </div>
                                            <div class="col-12 col-md-5">
                                                <q-input v-model="input.application_rate_per_ha" outlined dense type="text" inputmode="decimal" :label="rate?.label">
                                                    <template v-slot:append>
                                                        <span v-if="getInputUnit(input.input_id)" class="text-caption text-grey-7">{{ getInputUnit(input.input_id) }}{{ rate?.suffix }}</span>
                                                    </template>
                                                </q-input>
                                            </div>
                                        </div>
                                    </q-item-section>
                                    <q-item-section side>
                                        <q-btn flat dense round icon="delete" size="sm" @click="removeInput(index)">
                                        </q-btn>
                                    </q-item-section>
                                </q-item>
                            </q-list>

                            <!-- Add Input -->
                            <div class="row items-center q-gutter-md">
                                <div class="col" style="min-width: 200px;">
                                    <q-select ref="inputSelect" v-model="newInput.input_id" :options="filteredAvailableInputs"
                                        option-value="id" option-label="name" outlined dense emit-value map-options use-input
                                        hide-selected fill-input @filter="filterInputs" label="<?php echo th('operations.select_input'); ?>">
                                        <template v-slot:after-options>
                                            <q-btn icon="add" color="primary" @click="showAddInputForm = true" v-close-popup
                                                class="full-width" label="<?php echo th('operations.add_new_input'); ?>">
                                            </q-btn>
                                        </template>
                                        <!-- after-options is not rendered when the list is
                                             empty, which is when the button is needed most. -->
                                        <template v-slot:no-option>
                                            <q-item>
                                                <q-item-section>
                                                    <q-btn icon="add" color="primary" @click="showAddInputForm = true" v-close-popup
                                                        class="full-width" label="<?php echo th('operations.add_new_input'); ?>">
                                                    </q-btn>
                                                </q-item-section>
                                            </q-item>
                                        </template>
                                    </q-select>
                                </div>
                                <div class="col" style="min-width: 100px;">
                                    <q-input v-model="newInput.application_rate_per_ha" outlined dense type="text" inputmode="decimal" :label="rate?.label">
                                        <template v-slot:append>
                                            <span v-if="getInputUnit(newInput.input_id)" class="text-caption text-grey-7">{{ getInputUnit(newInput.input_id) }}{{ rate?.suffix }}</span>
                                        </template>
                                    </q-input>
                                </div>
                                <div class="col-auto">
                                    <q-btn color="primary" icon="add" round :disable="!newInput.input_id || !newInput.application_rate_per_ha" @click="addInput">
                                    </q-btn>
                                </div>
                            </div>
                        </div>
                    </form>
                </q-card-section>
                <q-separator></q-separator>
                <q-card-actions align="right">
                    <q-btn v-if="isEditing" icon="delete" label="<?php echo th('common.delete'); ?>" @click="handleDelete">
                    </q-btn>
                    <q-space></q-space>
                    <q-btn label="<?php echo th('common.cancel'); ?>" @click="closeForm"></q-btn>
                    <q-btn label="<?php echo th('common.save'); ?>" color="primary" @click="handleSubmit" :loading="saving"
                        :disable="!selectedTpr">
                    </q-btn>
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Add Input Dialog -->
        <q-dialog v-model="showAddInputForm">
            <q-card class="form-card" style="width: 500px; max-width: 90vw;">
                <q-card-section class="modal-header row items-center">
                    <div class="text-h6"><?php echo th('operations.add_new_input'); ?></div>
                    <q-space></q-space>
                    <q-btn icon="close" flat round dense @click="showAddInputForm = false"></q-btn>
                </q-card-section>
                <q-separator></q-separator>
                <q-card-section class="form-scroll">
                    <div class="q-mb-md">
                        <label class="q-mb-xs block text-weight-500"><?php echo th('operations.input_name'); ?></label>
                        <q-input v-model="newInputForm.name" outlined dense placeholder="<?php echo th('operations.input_name_ph'); ?>">
                        </q-input>
                    </div>
                    <div class="q-mb-md">
                        <label class="q-mb-xs block text-weight-500"><?php echo th('operations.description'); ?></label>
                        <q-input v-model="newInputForm.description" outlined dense type="textarea" placeholder="<?php echo th('operations.description_ph'); ?>">
                        </q-input>
                    </div>
                    <div class="q-mb-md">
                        <label class="q-mb-xs block text-weight-500"><?php echo th('operations.unit'); ?></label>
                        <q-input v-model="newInputForm.unit" outlined dense placeholder="<?php echo th('operations.unit_ph'); ?>">
                        </q-input>
                    </div>
                    <div v-if="newInputError" class="text-negative q-mb-md">{{ newInputError }}</div>
                </q-card-section>
                <q-separator></q-separator>
                <q-card-actions align="right">
                    <q-btn label="<?php echo th('common.cancel'); ?>" @click="showAddInputForm = false"></q-btn>
                    <q-btn label="<?php echo th('common.save'); ?>" color="primary" @click="handleAddNewInput" :loading="addingInput">
                    </q-btn>
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>

    <!-- Vue 3 + Quasar -->
    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.42/dist/vue.global.prod.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.umd.prod.js"></script>

    <script>
        // The rate inputs are plain text, so a pt-PT keyboard's comma arrives as
        // typed. Everything downstream wants a dot.
        function opsDot(value) {
            return String(value ?? '').trim().replace(',', '.');
        }

        // The column carries four decimals; Number() drops the trailing zeros
        // without capping precision, so 0.0050 stays 0.005.
        function opsRate(value) {
            return value === null || value === undefined || value === '' ? value : Number(value);
        }

        const app = Vue.createApp({
            data() {
                return {
                    // Data
                    cropsOperations: [],
                    crops: [],
                    animals: [],
                    operationTypes: [],
                    inputs: [],
                    loading: false,
                    filter: '',
                    tab: 'operations',

                    // Auth & KRD
                    krdValue: '',
                    aoiValue: '',
                    authToken: '',

                    // Form
                    showForm: false,
                    // True while openForm() populates the form. The selectedTpr
                    // watcher clears the target on a real type change, and
                    // loading a saved operation is not one.
                    loadingForm: false,
                    selectedItem: null,
                    form: {
                        id: null,
                        crop_id: null,
                        crop_ids: [],
                        animal_id: null,
                        animal_ids: [],
                        irrigation_mm: null,
                        harvest_qty: null,
                        operation_type_id: null,
                        operation_date: null,
                        inputs: []
                    },
                    errors: {
                        crop_id: null,
                        crop_ids: null,
                        animal_id: null,
                        animal_ids: null,
                        irrigation_mm: null,
                        harvest_qty: null,
                        operation_type_id: null,
                        operation_date: null
                    },
                    filteredCrops: [],
                    filteredAnimals: [],
                    filteredOperationTypes: [],
                    filteredAvailableInputs: [],

                    // Images
                    cameraInputRef: null,
                    scoutingImageRows: [],
                    scoutingImageRowCounter: 0,

                    // Inputs
                    newInput: {
                        input_id: null,
                        application_rate_per_ha: null
                    },
                    showAddInputForm: false,
                    addingInput: false,
                    // Set by applyProposal() when a draft arrives from the assistant;
                    // drives the banner above the form and nothing else.
                    proposal: null,
                    newInputError: '',
                    newInputForm: {
                        name: '',
                        description: '',
                        unit: ''
                    },
                    saving: false,

                    // Slack around a crop's cycle in which an operation is still
                    // plausible. Drives dateWarnings() and nothing else.
                    PREP_DAYS: <?php echo (int) PLAN_PREP_DAYS; ?>,
                    CLEANUP_DAYS: <?php echo (int) PLAN_CLEANUP_DAYS; ?>,

                    // API
                    API_URL: '<?php echo OPS_API_URL; ?>',
                    // Agent layer. OPS_API_URL still serves every data
                    // endpoint this page uses; only the AI call moved.
                    MEDIA_URL: '<?php echo OPS_MEDIA_URL; ?>'
                };
            },
            computed: {
                isEditing() {
                    return !!this.form.id;
                },
                columns() {
                    return [{
                            name: 'id',
                            label: <?php echo tj('operations.col_id'); ?>,
                            field: 'id',
                            align: 'left',
                            sortable: true
                        },
                        {
                            name: 'cultura',
                            label: <?php echo tj('operations.col_production'); ?>,
                            field: 'production_name',
                            align: 'left',
                            sortable: true
                        },
                        {
                            name: 'talhao',
                            label: <?php echo tj('operations.col_field'); ?>,
                            field: 'field_name',
                            align: 'left',
                            sortable: true
                        },
                        {
                            name: 'operacao',
                            label: <?php echo tj('operations.col_operation_type'); ?>,
                            field: 'operation_type_name',
                            align: 'left',
                            sortable: true
                        },
                        {
                            name: 'data',
                            label: <?php echo tj('operations.col_date'); ?>,
                            field: 'operation_date',
                            align: 'left',
                            sortable: true
                        }
                    ];
                },
                // '' until an operation type is picked, which is what keeps both
                // target panes hidden.
                selectedTpr() {
                    const type = this.operationTypes.find(o => Number(o.id) === Number(this.form.operation_type_id));
                    return type ? type.tpr : '';
                },
                // The selected crop's production unit; a harvest covers one crop.
                harvestUnit() {
                    const cropId = this.isEditing ? this.form.crop_id : (this.form.crop_ids || [])[0];
                    const crop = this.crops.find(c => Number(c.crop_id) === Number(cropId));
                    return crop ? crop.production_unit : '';
                },
                tprInputs() {
                    if (!this.selectedTpr) return [];
                    return this.inputs.filter(input => input.tpr === this.selectedTpr);
                },
                // What the rate is measured against. One entry per form.
                rateByTpr() {
                    return {
                        v: { label: <?php echo tj('operations.rate_per_ha'); ?>, suffix: '/ha' },
                        a: { label: <?php echo tj('operations.rate_per_head'); ?>, suffix: '/animal' },
                        // Fertigation inputs.
                        i: { label: <?php echo tj('operations.rate_per_ha'); ?>, suffix: '/ha' }
                    };
                },
                rate() {
                    return this.rateByTpr[this.selectedTpr];
                },
                availableInputs() {
                    return this.tprInputs.filter(input => !this.form.inputs.some(fi => fi.input_id === input.id));
                },
                // Advisory only, and deliberately not in errors: a date outside
                // the usual window is unusual, not wrong. Both checks read the
                // crop list this page already holds, so neither costs a request.
                dateWarnings() {
                    const date = this.form.operation_date;
                    if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) return [];

                    const selected = this.isEditing
                        ? (this.form.crop_id ? [this.form.crop_id] : [])
                        : (this.form.crop_ids || []);

                    const warnings = [];
                    selected.forEach(id => {
                        const crop = this.crops.find(c => Number(c.crop_id) === Number(id));
                        if (!crop || !crop.dti) return;
                        const cycle = Number(crop.cycle_days || 0);
                        const label = this.cropLabel(crop);

                        if (date < this.shiftDate(crop.dti, -this.PREP_DAYS) ||
                            date > this.shiftDate(crop.dti, cycle + this.CLEANUP_DAYS)) {
                            warnings.push(`${<?php echo tj('operations.date_outside'); ?>} ${label}`);
                        }

                        // A date sitting inside a different crop's cycle on the
                        // same field more likely belongs to that crop.
                        const other = this.crops.find(c =>
                            Number(c.crop_id) !== Number(crop.crop_id) &&
                            Number(c.field_id) === Number(crop.field_id) &&
                            c.dti && date >= c.dti &&
                            date <= this.shiftDate(c.dti, Number(c.cycle_days || 0)));
                        if (other) {
                            warnings.push(`${<?php echo tj('operations.date_other_crop'); ?>} ${this.cropLabel(other)}`);
                        }
                    });
                    return warnings;
                }
            },
            methods: {
                showNotification(message, color, icon) {
                    this.$q.notify({
                        message: message,
                        color: color === 'positive' ? 'positive' : (color === 'warning' ? 'warning' : 'negative'),
                        icon: icon || 'error',
                        position: 'top',
                        timeout: 4000,
                        html: true
                    });
                },
                // True when a fetch failed because the request never reached the
                // server. fetch() rejects with a TypeError for that, and only
                // for that - a non-JSON body throws SyntaxError, and an HTTP
                // error status does not throw at all - so the two cases stay
                // apart. navigator.onLine catches a device with no link before
                // the request is even attempted.
                isNetworkError(e) {
                    return !navigator.onLine || e instanceof TypeError;
                },

                // Hands a lost connection to the shell. window.top, not
                // window.parent: this page is embedded as a fields.php tab, so
                // window.parent is fields.php and the shell is one level above
                // it. Returns false when the shell is not there (page opened
                // directly), so the caller falls back to its own notification.
                //
                // Asks rather than navigating straight to the offline page:
                // this is called from the save path, and leaving the shell
                // would discard whatever the user had typed into the form.
                reportOffline() {
                    if (window.top === window || typeof window.top.mskDialog !== 'function') return false;
                    window.top.mskDialog({
                        title: <?php echo tj('common.network_error'); ?>,
                        message: <?php echo tj('help.offline'); ?>,
                        cancel: true
                    }).onOk(() => window.top.mskOffline());
                    return true;
                },


                // Build request headers with KRD and Authorization
                getRequestHeaders() {
                    const headers = {
                        'Accept': 'application/json'
                    };
                    if (this.authToken) {
                        headers['Authorization'] = 'Bearer ' + this.authToken;
                    }
                    return headers;
                },

                // Build API URL with krd query parameter
                buildApiUrl(endpoint, params = {}) {
                    const url = new URL(`${this.API_URL}${endpoint}`);
                    if (this.krdValue) {
                        params.krd = this.krdValue;
                    }
                    Object.keys(params).forEach(key => {
                        url.searchParams.append(key, params[key]);
                    });
                    return url.toString();
                },

                async fetchCropsOperations() {
                    this.loading = true;
                    try {
                        const url = this.buildApiUrl('/crops_operations.php?action=read');
                        const response = await fetch(url, {
                            headers: this.getRequestHeaders()
                        });
                        const data = await response.json();
                        if (data.success && data.data) {
                            this.cropsOperations = data.data.filter(op => op.id && (op.production_name || op.animal_name) && op.operation_type_name && op.operation_date);
                        } else {
                            this.cropsOperations = [];
                        }
                    } catch (error) {
                        console.error('Error fetching crops operations:', error);
                        this.cropsOperations = [];
                        this.showNotification(<?php echo tj('operations.load_failed'); ?>, 'negative', 'cloud_off');
                    } finally {
                        this.loading = false;
                    }
                },

                async fetchCrops() {
                    try {
                        const params = this.aoiValue ? { aoi: this.aoiValue } : {};
                        const url = this.buildApiUrl('/field-crops.php?action=read', params);
                        const response = await fetch(url, {
                            headers: this.getRequestHeaders()
                        });
                        const data = await response.json();
                        this.crops = data.data || [];
                        this.filteredCrops = this.crops;
                    } catch (error) {
                        console.error('Error loading field crops:', error);
                    }
                },

                cropLabel(crop) {
                    if (!crop) return '';
                    return `${crop.production_name} — ${crop.field_name} (${crop.dti} → ${crop.end_date})`;
                },

                async fetchAnimals() {
                    try {
                        const url = this.buildApiUrl('/animals.php?action=read');
                        const response = await fetch(url, {
                            headers: this.getRequestHeaders()
                        });
                        const data = await response.json();
                        this.animals = data.data || [];
                        this.filteredAnimals = this.animals;
                    } catch (error) {
                        console.error('Error loading animals:', error);
                    }
                },

                animalLabel(animal) {
                    if (!animal) return '';
                    return animal.description ? `${animal.name} — ${animal.description}` : animal.name;
                },

                filterAnimals(val, update) {
                    update(() => {
                        const needle = val.toLowerCase();
                        this.filteredAnimals = this.animals.filter(a =>
                            this.animalLabel(a).toLowerCase().includes(needle));
                    });
                },

                async fetchOperationTypes() {
                    try {
                        const url = this.buildApiUrl('/operation_types.php?action=read');
                        const response = await fetch(url, {
                            headers: this.getRequestHeaders()
                        });
                        const data = await response.json();
                        this.operationTypes = data.data || [];
                        this.filteredOperationTypes = this.operationTypes;
                    } catch (error) {
                        console.error('Error loading operation types:', error);
                    }
                },

                async fetchInputs() {
                    try {
                        const url = this.buildApiUrl('/inputs.php?action=read');
                        const response = await fetch(url, {
                            headers: this.getRequestHeaders()
                        });
                        const data = await response.json();
                        this.inputs = data.data || [];
                        this.filteredAvailableInputs = this.availableInputs;
                    } catch (error) {
                        console.error('Error loading inputs:', error);
                    }
                },

                openForm(item = null) {
                    this.loadingForm = true;
                    this.selectedItem = item;
                    this.form = {
                        id: item?.id || null,
                        crop_id: item?.crop_id || null,
                        crop_ids: [],
                        animal_id: item?.animal_id || null,
                        animal_ids: [],
                        irrigation_mm: opsRate(item?.irrigation_mm) ?? null,
                        harvest_qty: opsRate(item?.harvest_qty) ?? null,
                        operation_type_id: item?.operation_type_id || null,
                        operation_date: item?.operation_date || this.getTodayDate(),
                        inputs: (item?.inputs || []).map(i => ({
                            ...i,
                            application_rate_per_ha: opsRate(i.application_rate_per_ha)
                        }))
                    };
                    this.errors = {
                        crop_id: null,
                        crop_ids: null,
                        animal_id: null,
                        animal_ids: null,
                        irrigation_mm: null,
                        harvest_qty: null,
                        operation_type_id: null,
                        operation_date: null
                    };
                    this.clearScoutingImages();
                    // Load existing images if editing
                    if (item?.images && item.images.length > 0) {
                        item.images.forEach(img => {
                            this.scoutingImageRows.push({
                                key: this.nextScoutingImageRowKey(),
                                id: img.id,
                                file: null,
                                note: img.notes || '',
                                fileName: img.file_name || '',
                                previewUrl: img.file_path ? this.MEDIA_URL + '/' + img.file_path : '',
                                originalPreviewUrl: img.file_path ? this.MEDIA_URL + '/' + img.file_path : '',
                                originalFileName: img.file_name || '',
                                previewObjectUrl: ''
                            });
                        });
                    }
                    this.showForm = true;
                    this.filteredCrops = this.crops;
                    this.filteredAnimals = this.animals;
                    this.filteredOperationTypes = this.operationTypes;
                    this.filteredAvailableInputs = this.availableInputs;
                    // applyProposal() fills the form synchronously after this
                    // returns, so the flag has to outlive the whole tick.
                    this.$nextTick(() => { this.loadingForm = false; });
                },

                closeForm() {
                    this.showForm = false;
                    this.selectedItem = null;
                    this.proposal = null;
                },

                // Receives a draft from the assistant pane via the shell's
                // mskProposeFill(). Goes through openForm() so a proposal lands in
                // exactly the state a blank form would, then overwrites only the
                // fields the assistant resolved - nothing here touches the DOM or
                // submits anything. The user reviews and presses Save, which runs
                // the same handleSubmit() as any manual entry.
                //
                // Every id arrives already checked against the tenant database by
                // the operation-fill validator, so anything the
                // assistant could not resolve is absent from the payload and listed
                // in unresolved[] for the banner rather than guessed at.
                applyProposal(payload) {
                    if (!payload || typeof payload !== 'object') return;

                    this.openForm(null);

                    // The selects use option-value with map-options, and the API
                    // hands ids back as strings, so Quasar's strict lookup misses
                    // a numeric 8 against an option valued "8" and renders the
                    // chip as "undefined — undefined".
                    // Match loosely, then store the option's own id verbatim so the
                    // type always agrees with whatever the catalogue supplied.
                    const idOf = (list, wanted, key = 'id') => {
                        const hit = list.find(o => Number(o[key]) === Number(wanted));
                        return hit ? hit[key] : null;
                    };

                    if (Array.isArray(payload.crop_ids)) {
                        this.form.crop_ids = payload.crop_ids
                            .map(id => idOf(this.crops, id, 'crop_id'))
                            .filter(id => id !== null);
                    }
                    if (payload.operation_type_id) {
                        this.form.operation_type_id = idOf(this.operationTypes, payload.operation_type_id);
                    }
                    if (payload.operation_date) this.form.operation_date = payload.operation_date;
                    if (Array.isArray(payload.inputs)) {
                        this.form.inputs = payload.inputs
                            .map(i => ({
                                input_id: idOf(this.inputs, i.input_id),
                                application_rate_per_ha: opsRate(i.application_rate_per_ha)
                            }))
                            .filter(i => i.input_id !== null);
                    }

                    // Photographs the user attached in the chat, with whatever the
                    // assistant observed in each. They arrive as data URLs, which
                    // the assistant pane resolved from the image numbers the model
                    // referred to - the bytes never went near the server's tool
                    // loop. Turning each back into a File is what makes the rest
                    // of this page treat them as ordinary picked images:
                    // createNewScoutingImageRow() builds the preview, and
                    // handleSubmit() already uploads any row whose file is a File.
                    if (Array.isArray(payload.scouting_images)) {
                        payload.scouting_images.forEach(image => {
                            const file = this.dataUrlToFile(image.data_url, image.file_name);
                            if (!file) return;
                            const row = this.createNewScoutingImageRow(file);
                            row.note = String(image.note || '');
                            this.scoutingImageRows.push(row);
                        });
                    }

                    this.proposal = {
                        unresolved: Array.isArray(payload.unresolved) ? payload.unresolved : [],
                        complete: payload.complete === true
                    };

                    this.$nextTick(() => {
                        const el = document.querySelector('.q-dialog');
                        if (el) el.scrollTop = 0;
                    });
                },

                // A data URL back to a File, so a photograph that came through the
                // chat is indistinguishable from one picked with the file input.
                // Returns null rather than throwing on anything malformed: a bad
                // image should cost its own row, not the whole draft.
                dataUrlToFile(dataUrl, fileName) {
                    const match = /^data:(image\/[a-z+]+);base64,(.+)$/i.exec(String(dataUrl || ''));
                    if (!match) return null;

                    try {
                        const binary = atob(match[2]);
                        const bytes = new Uint8Array(binary.length);
                        for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
                        const extension = match[1].split('/')[1].replace('jpeg', 'jpg');
                        return new File([bytes], fileName || `scouting-${Date.now()}.${extension}`, { type: match[1] });
                    } catch (e) {
                        console.error('Could not rebuild the proposed image:', e);
                        return null;
                    }
                },

                getTodayDate() {
                    const today = new Date();
                    return today.toISOString().split('T')[0];
                },

                // UTC throughout. Parsing 'YYYY-MM-DD' as local time and adding
                // days crosses daylight saving and lands an hour out, which
                // rounds to the wrong day twice a year.
                shiftDate(date, days) {
                    if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) return '';
                    const shifted = new Date(date + 'T00:00:00Z');
                    if (Number.isNaN(shifted.getTime())) return '';
                    shifted.setUTCDate(shifted.getUTCDate() + Number(days || 0));
                    return shifted.toISOString().split('T')[0];
                },

                filterCrops(val, update) {
                    update(() => {
                        const needle = val.toLowerCase();
                        this.filteredCrops = this.crops.filter(fc =>
                            fc.production_name.toLowerCase().includes(needle) || fc.field_name.toLowerCase().includes(needle));
                    });
                },

                filterOperationTypes(val, update) {
                    update(() => {
                        const needle = val.toLowerCase();
                        this.filteredOperationTypes = this.operationTypes.filter(op => op.name.toLowerCase().includes(needle));
                    });
                },

                filterInputs(val, update) {
                    update(() => {
                        const needle = val.toLowerCase();
                        this.filteredAvailableInputs = this.availableInputs.filter(input => input.name.toLowerCase().includes(needle));
                    });
                },

                validateField(field) {
                    this.errors[field] = null;
                    if (field === 'crop_id' && this.isEditing && !this.form.crop_id) this.errors[field] = <?php echo tj('operations.select_crop'); ?>;
                    if (field === 'crop_ids' && !this.isEditing && (!this.form.crop_ids || this.form.crop_ids.length === 0)) this.errors[field] = <?php echo tj('operations.select_crop_min'); ?>;
                    if (field === 'animal_id' && this.isEditing && !this.form.animal_id) this.errors[field] = <?php echo tj('operations.select_animal'); ?>;
                    if (field === 'animal_ids' && !this.isEditing && (!this.form.animal_ids || this.form.animal_ids.length === 0)) this.errors[field] = <?php echo tj('operations.select_animal_min'); ?>;
                    if (field === 'operation_type_id' && !this.form.operation_type_id) this.errors[field] = <?php echo tj('operations.select_operation_type'); ?>;
                    if (field === 'operation_date') {
                        if (!this.form.operation_date) this.errors[field] = <?php echo tj('common.required_field'); ?>;
                        else if (!/^\d{4}-\d{2}-\d{2}$/.test(this.form.operation_date)) this.errors[field] = <?php echo tj('operations.invalid_date'); ?>;
                    }
                },

                getInputUnit(inputId) {
                    const input = this.inputs.find(i => Number(i.id) === Number(inputId));
                    return input?.unit || '';
                },

                getAvailableInputsForRow(rowIndex) {
                    const row = this.form.inputs[rowIndex] || {};
                    const selectedInputId = Number(row.input_id || 0);
                    return this.tprInputs.filter((input) => {
                        const candidateId = Number(input.id);
                        if (candidateId === selectedInputId) return true;
                        return !this.form.inputs.some((otherInput, otherIndex) => otherIndex !== rowIndex && Number(otherInput?.input_id || 0) === candidateId);
                    });
                },

                addInput() {
                    const inputId = this.newInput.input_id;
                    const numericInputId = Number(inputId || 0);
                    const ratePerHa = Number(opsDot(this.newInput.application_rate_per_ha));
                    if (!inputId || numericInputId <= 0 || !ratePerHa || ratePerHa <= 0) return;
                    if (this.form.inputs.some(input => Number(input.input_id) === numericInputId)) return;
                    this.form.inputs.push({
                        input_id: inputId,
                        application_rate_per_ha: ratePerHa
                    });
                    this.newInput = {
                        input_id: null,
                        application_rate_per_ha: null
                    };
                },

                removeInput(index) {
                    this.form.inputs.splice(index, 1);
                },

                handleAddNewInput() {
                    if (!this.newInputForm.name.trim()) {
                        this.newInputError = <?php echo tj('operations.name_required'); ?>;
                        return;
                    }
                    if (!this.newInputForm.unit.trim()) {
                        this.newInputError = <?php echo tj('operations.unit_required'); ?>;
                        return;
                    }
                    this.addingInput = true;
                    const url = this.buildApiUrl('/inputs.php?action=create');
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            ...this.getRequestHeaders(),
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            name: this.newInputForm.name,
                            description: this.newInputForm.description || '',
                            unit: this.newInputForm.unit,
                            // The new input belongs to the side being recorded.
                            tpr: this.selectedTpr
                        })
                    }).then(r => r.json()).then(data => {
                        if (data.success && data.data) {
                            this.inputs.push(data.data);
                            this.filteredAvailableInputs = this.availableInputs;
                            // Selected in the row behind this dialog: the user
                            // opened it to use this input, not to file it away.
                            this.newInput.input_id = data.data.id;
                            this.newInputForm = {
                                name: '',
                                description: '',
                                unit: ''
                            };
                            this.showAddInputForm = false;
                            this.addingInput = false;
                            this.showNotification(<?php echo tj('operations.input_created'); ?>, 'positive', 'check');
                        } else {
                            this.newInputError = data.message || <?php echo tj('operations.input_failed'); ?>;
                            this.addingInput = false;
                        }
                    }).catch(() => {
                        this.newInputError = <?php echo tj('operations.network_error_short'); ?>;
                        this.addingInput = false;
                    });
                },

                handleDelete() {
                    if (!this.form.id) return;
                    this.$q?.dialog?.({
                            title: <?php echo tj('common.confirm_delete'); ?>,
                            message: <?php echo tj('operations.delete_confirm'); ?>,
                            cancel: true,
                            persistent: true
                        })
                        ?.onOk?.(() => {
                            const url = this.buildApiUrl('/crops_operations.php?action=delete');
                            fetch(url, {
                                method: 'POST',
                                headers: {
                                    ...this.getRequestHeaders(),
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    id: this.form.id
                                })
                            }).then(r => r.json()).then(data => {
                                if (data.success) {
                                    this.showNotification(<?php echo tj('operations.deleted'); ?>, 'positive', 'check');
                                    this.closeForm();
                                    this.fetchCropsOperations();
                                } else {
                                    this.showNotification(data.message || <?php echo tj('operations.delete_failed'); ?>, 'negative', 'error');
                                }
                            }).catch(() => this.showNotification(<?php echo tj('common.network_error'); ?>, 'negative', 'cloud_off'));
                        });
                },

                // Image methods
                addEmptyScoutingImageRow() {
                    this.scoutingImageRows.push(this.createNewScoutingImageRow());
                },
                nextScoutingImageRowKey() {
                    this.scoutingImageRowCounter++;
                    return `scouting-image-${Date.now()}-${this.scoutingImageRowCounter}`;
                },
                createNewScoutingImageRow(initialFile = null) {
                    const row = {
                        key: this.nextScoutingImageRowKey(),
                        id: null,
                        file: null,
                        note: '',
                        fileName: '',
                        previewUrl: '',
                        originalPreviewUrl: '',
                        originalFileName: '',
                        previewObjectUrl: ''
                    };
                    if (initialFile instanceof File) {
                        row.file = initialFile;
                        row.fileName = initialFile.name;
                        row.previewUrl = URL.createObjectURL(initialFile);
                    }
                    return row;
                },
                updateScoutingImageRowFile(index, fileValue) {
                    if (index < 0 || index >= this.scoutingImageRows.length) return;
                    const row = this.scoutingImageRows[index];
                    if (fileValue instanceof File) {
                        if (row.previewObjectUrl) URL.revokeObjectURL(row.previewObjectUrl);
                        row.file = fileValue;
                        row.fileName = fileValue.name;
                        row.previewUrl = URL.createObjectURL(fileValue);
                    }
                },
                openCameraCapture() {
                    this.$refs?.cameraInputRef?.click();
                },
                handleCameraCapture(event) {
                    const file = Array.from(event?.target?.files || []).find(f => f instanceof File && f.type.startsWith('image/'));
                    if (file) this.addScoutingImageRowWithFile(file);
                    if (event?.target) event.target.value = '';
                },
                addScoutingImageRowWithFile(file) {
                    if (file instanceof File && file.type.startsWith('image/')) this.scoutingImageRows.push(this.createNewScoutingImageRow(file));
                },
                removeScoutingImageRow(index) {
                    if (index < 0 || index >= this.scoutingImageRows.length) return;
                    const row = this.scoutingImageRows.splice(index, 1)[0];
                    if (row?.previewObjectUrl) URL.revokeObjectURL(row.previewObjectUrl);
                },
                clearScoutingImages() {
                    this.scoutingImageRows.forEach(r => {
                        if (r?.previewObjectUrl) URL.revokeObjectURL(r.previewObjectUrl);
                    });
                    this.scoutingImageRows = [];
                },

                async handleSubmit() {
                    this.errors = {
                        crop_id: null,
                        crop_ids: null,
                        animal_id: null,
                        animal_ids: null,
                        irrigation_mm: null,
                        harvest_qty: null,
                        operation_type_id: null,
                        operation_date: null
                    };
                    if (this.selectedTpr === 'i' && !(Number(opsDot(this.form.irrigation_mm)) > 0)) {
                        this.errors.irrigation_mm = <?php echo tj('operations.irrigation_mm_invalid'); ?>;
                    }
                    if (this.selectedTpr === 'h' && !(Number(opsDot(this.form.harvest_qty)) > 0)) {
                        this.errors.harvest_qty = <?php echo tj('operations.harvest_qty_invalid'); ?>;
                    }
                    if (this.selectedTpr === 'a') {
                        if (this.isEditing && !this.form.animal_id) this.errors.animal_id = <?php echo tj('operations.select_animal'); ?>;
                        if (!this.isEditing && (!this.form.animal_ids || this.form.animal_ids.length === 0)) this.errors.animal_ids = <?php echo tj('operations.select_animal_min'); ?>;
                    } else {
                        if (this.isEditing && !this.form.crop_id) this.errors.crop_id = <?php echo tj('operations.select_crop'); ?>;
                        if (!this.isEditing && (!this.form.crop_ids || this.form.crop_ids.length === 0)) this.errors.crop_ids = <?php echo tj('operations.select_crop_min'); ?>;
                    }
                    if (!this.form.operation_type_id) this.errors.operation_type_id = <?php echo tj('operations.select_operation_type'); ?>;
                    if (!this.form.operation_date) this.errors.operation_date = <?php echo tj('common.required_field'); ?>;
                    if (Object.values(this.errors).some(e => e)) return;

                    this.saving = true;
                    try {
                        const action = this.form.id ? 'update' : 'create';
                        const formData = new FormData();
                        if (this.selectedTpr === 'i') formData.append('irrigation_mm', opsDot(this.form.irrigation_mm));
                        if (this.selectedTpr === 'h') formData.append('harvest_qty', opsDot(this.form.harvest_qty));
                        formData.append('operation_type_id', String(this.form.operation_type_id || ''));
                        formData.append('operation_date', String(this.form.operation_date || ''));
                        formData.append('inputs', JSON.stringify((this.form.inputs || []).map(i => ({
                            ...i,
                            application_rate_per_ha: Number(opsDot(i.application_rate_per_ha))
                        }))));
                        if (action === 'update') {
                            formData.append('id', String(this.form.id || ''));
                            if (this.selectedTpr === 'a') {
                                formData.append('animal_id', String(this.form.animal_id || ''));
                            } else {
                                formData.append('crop_id', String(this.form.crop_id || ''));
                            }
                        } else if (this.selectedTpr === 'a') {
                            formData.append('animal_ids', JSON.stringify(this.form.animal_ids || []));
                        } else {
                            formData.append('crop_ids', JSON.stringify(this.form.crop_ids || []));
                        }
                        const selectedImages = this.scoutingImageRows.filter((img, i) => img.id || img.file instanceof File);
                        selectedImages.forEach((img, i) => {
                            if (img.file instanceof File) formData.append('images[]', img.file);
                        });
                        formData.append('image_rows', JSON.stringify(selectedImages.map((img, i) => ({
                            id: img.id,
                            note: img.note,
                            file_upload_index: img.file ? i : null
                        }))));

                        const url = this.buildApiUrl('/crops_operations.php?action=' + action);
                        const response = await fetch(url, {
                            method: 'POST',
                            body: formData,
                            headers: this.getRequestHeaders()
                        });
                        const data = await response.json();
                        if (data.success) {
                            this.showNotification(action === 'create' ? <?php echo tj('operations.created'); ?> : <?php echo tj('operations.updated'); ?>, 'positive', 'check');
                            this.closeForm();
                            this.fetchCropsOperations();
                        } else {
                            this.showNotification(data.message || <?php echo tj('operations.save_failed'); ?>, 'negative', 'error');
                        }
                    } catch (e) {
                        // A save that never left the device is a connectivity
                        // problem, not a validation one - "Failed to fetch" in
                        // a toast tells the user nothing they can act on.
                        if (this.isNetworkError(e) && this.reportOffline()) {
                            // Shell has it. The form stays as it is so the
                            // user can retry once the connection is back.
                        } else {
                            this.showNotification(<?php echo tj('operations.error_prefix'); ?> + e.message, 'negative', 'error');
                        }
                    } finally {
                        this.saving = false;
                    }
                },

                handleRowClick(event, row) {
                    this.openForm(row);
                }
            },
            watch: {
                // Switching sides strands the target and the input rows: both
                // belong to the tpr that was in force when they were picked.
                selectedTpr() {
                    if (this.loadingForm) return;
                    this.form.crop_id = null;
                    this.form.crop_ids = [];
                    this.form.animal_id = null;
                    this.form.animal_ids = [];
                    this.form.irrigation_mm = null;
                    this.form.harvest_qty = null;
                    this.form.inputs = [];
                    this.newInput = { input_id: null, application_rate_per_ha: null };
                    this.filteredAvailableInputs = this.availableInputs;
                }
            },
            mounted() {
                // Prefer krd/aoi from this page's own URL (embedded as a fields.php tab),
                // falling back to the parent window (standalone/legacy embedding).
                const params = new URLSearchParams(window.location.search);
                const krd = params.get('krd') || window.parent.krd;
                if (krd) {
                    this.krdValue = krd;
                } else {
                    this.showNotification(<?php echo tj('common.krd_missing'); ?>, 'warning', 'warning');
                }
                this.aoiValue = params.get('aoi') || '';

                // Initialize auth token (same as fields.php)
                this.authToken = localStorage.getItem('auth_token') || '';

                this.fetchCropsOperations();
                this.fetchCrops();
                this.fetchAnimals();
                this.fetchOperationTypes();
                this.fetchInputs();

                // Registered with the shell so the assistant pane can hand this page
                // a draft. Guarded because app/*.php can also be opened directly,
                // where window.top is this page itself. Registration waits for the
                // catalogues above so applyProposal() can validate ids against them;
                // the shell queues any proposal that arrives before this runs.
                if (window.top !== window && typeof window.top.mskRegisterProposalTarget === 'function') {
                    Promise.all([this.fetchCrops(), this.fetchOperationTypes(), this.fetchInputs()])
                        .catch(() => { /* register anyway — a partial catalogue still beats no delivery */ })
                        .finally(() => {
                            window.top.mskRegisterProposalTarget('operations', (payload) => this.applyProposal(payload));
                        });

                    window.addEventListener('beforeunload', () => {
                        if (typeof window.top.mskUnregisterProposalTarget === 'function') {
                            window.top.mskUnregisterProposalTarget('operations');
                        }
                    });
                }
            }
        });

        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>

</html>