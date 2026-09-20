<?php
// goa/app/fields.php - Fields map page using Quasar + Leaflet
require_once 'config.php';
require_once __DIR__ . '/lang.php';
$pageTitle = t('fields.page_title');
?>
<!DOCTYPE html>
<html lang="<?php echo lang_code(); ?>">

<head>
    <?php require __DIR__ . '/app-head.php'; ?>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin="" />

    <style>
        body {
            margin: 0;
            padding: 0;
        }

        #map {
            width: 100%;
            height: calc(100vh - 100px);
            min-height: 300px;
        }

        /*
         * Full viewport: q-page-container gets padding-top = the header height
         * (50px, from .ag-base-q-header in app-head.php) and q-page is sized to
         * calc(100vh - 50px), so the layout needs the whole 100vh. Capping this
         * smaller is what made the page overflow no matter what q-page did.
         */
        #q-app {
            height: 100vh;
        }

        .field-popup strong {
            font-size: 14px;
        }

        .field-popup {
            font-size: 13px;
            line-height: 1.6;
        }

        .vegetation-iframe {
            width: 100%;
            height: calc(100vh - 60px);
            border: none;
        }

        /* Dialog must fully overlap fields.php content with zero padding */
        .dialog-full-cover .q-dialog__inner {
            padding: 0 !important;
        }

        /* Quasar marks selection by greying the label and nothing else, which
           reads as disabled. Green background instead, label left black. */
        .q-tree__node-header.q-tree__node--selected {
            background: rgba(130, 180, 70, 0.3);
        }

        /* Same specificity as Quasar's own rule, and this sheet loads after. */
        .q-tree__node--selected .q-tree__node-header-content {
            color: #000;
        }
    </style>
</head>

<body>
    <div id="q-app">
        <q-layout view="hHh lpR fFf">
            <!-- Header / Navbar -->
            <q-header class="ag-base-q-header">
                <q-toolbar>
                    <q-space></q-space>
                    <q-toolbar-title shrink>
                        <q-icon name="grass" size="md" class="q-mr-sm"></q-icon>
                        <?php echo th('fields.toolbar'); ?>
                    </q-toolbar-title>
                    <q-space></q-space>
                </q-toolbar>
            </q-header>

            <!-- Page Content -->
            <q-page-container >
                <q-page class="q-pa-md" style="height: calc(100vh - 50px); overflow-y: auto">

                    <!-- Linear Progress during loading -->
                    <q-linear-progress
                        v-if="loading"
                        color="primary"
                        class="q-mb-md"
                        indeterminate>
                    </q-linear-progress>

                    <!-- Responsive Layout: Map + Tree -->
                    <div class="row q-col-gutter-md">
                        <!-- Tree Column (full width on mobile, 4 cols on desktop) -->
                        <div class="col-12 col-sm-6">
                            <q-card flat bordered style="height: 100%">
                                <q-card-section class="q-pa-none">
                                    <!-- Outside the tree's v-if: a farm with no fields
                                         must still be able to add its first. -->
                                    <div class="row items-center q-gutter-sm no-wrap q-pa-sm">
                                        <q-btn
                                            icon="add"
                                            label="<?php echo th('fields.add'); ?>"
                                            color="primary"
                                            :disable="loading || !krdValue"
                                            @click="showAddChoice = true">
                                        </q-btn>
                                        <q-btn
                                            icon="add"
                                            label="<?php echo th('fields.add_crop'); ?>"
                                            color="primary"
                                            :disable="!selectedFieldNode"
                                            @click="openAddCrop(selectedFieldNode)">
                                        </q-btn>
                                        <q-space></q-space>
                                    </div>
                                    <div style="width: 100%; height: calc(100vh - 166px); min-height: 214px; margin: 10px 0px 10px 0px; overflow-y: auto; border: 0px;">
                                        <q-tree
                                            v-if="treeData.length > 0"
                                            :nodes="treeData"
                                            node-key="id"
                                            label-key="label"
                                            :selected="selectedNodeKey"
                                            @update:selected="selectedNodeKey = $event"
                                            squared
                                            dense>
                                            <!--
                                                default-header, not default-node: QTree has no
                                                "node" slot, so the template that used to be here
                                                was silently ignored and the area caption below
                                                never rendered.
                                            -->
                                            <template v-slot:default-header="props">
                                                <q-item class="full-width q-pa-none" :style="rowIndent(props.node)">
                                                    <q-item-section avatar>
                                                        <!-- :style, not :color: the ochre is not in Quasar's palette. -->
                                                        <q-icon :name="props.node.icon" :style="{ color: props.node.color }"></q-icon>
                                                    </q-item-section>
                                                    <q-item-section>
                                                        <q-item-label>{{ props.node.label }}</q-item-label>
                                                        <q-item-label caption>{{ props.node.areaCaption }}</q-item-label>
                                                    </q-item-section>
                                                    <q-item-section side>
                                                        <q-btn
                                                            v-if="!props.node.cropData"
                                                            icon="center_focus_strong"
                                                            flat
                                                            round
                                                            dense
                                                            size="sm"
                                                            @click.stop="handleFieldSelect(props.node.id)">
                                                        </q-btn>
                                                    </q-item-section>
                                                    <q-item-section side>
                                                        <q-btn
                                                            icon="edit"
                                                            flat
                                                            round
                                                            dense
                                                            size="sm"
                                                            @click.stop="props.node.cropData ? openEditCrop(props.node) : openEditField(props.node)">
                                                        </q-btn>
                                                    </q-item-section>
                                                </q-item>
                                            </template>
                                        </q-tree>
                                        <div v-else class="text-center q-pa-md">
                                            <q-icon name="info" size="50px" color="grey-5"></q-icon>
                                            <div class="text-grey-7 q-mt-sm"><?php echo th('fields.empty'); ?></div>
                                        </div>
                                    </div>
                                </q-card-section>
                            </q-card>
                        </div>
                                                <!-- Map Column (full width on mobile, 8 cols on desktop) -->
                        <div class="col-12 col-sm-6">
                            <q-card flat bordered>
                                <q-card-section class="q-pa-none">
                                    <div id="map"></div>
                                </q-card-section>
                            </q-card>
                        </div>
                    </div>
                </q-page>
            </q-page-container>
        </q-layout>


        <!-- Fullscreen Modal Dialog for Vegetation Analysis -->
        <q-dialog
            v-model="showFieldModal"
            persistent
            full-screen
            class="dialog-full-cover"
            @before-leave="onModalBeforeLeave">
            <q-card style="width: 100vw; height: calc(100vh + 10px); max-width: 100vw; max-height: 100vh;">
                <q-card-section class="modal-header row items-center">
                    <div class="text-h6">{{ selectedFieldName }}</div>
                    <q-space></q-space>
                    <q-btn icon="close" flat round dense v-close-popup @click="closeVegetationModal"></q-btn>
                </q-card-section>
                <q-card-section class="q-pa-none" style="height: calc(100vh - 50px); padding: 0;">
                    <!-- q-tabs for switching between vegetation and climate -->
                    <q-tabs
                        v-model="modalTab"
                        class="bg-white text-primary"
                        dense
                        narrow
                        indicator-color="primary"
                        active-bg-color="white"
                        textColor="grey-8"
                        inactive-color="grey-7"
                        align="left"
                        stretch>
                        <q-tab name="vegetation" icon="eco" label="<?php echo th('fields.tab_vegetation'); ?>"></q-tab>
                        <q-tab name="climate" icon="cloud" label="<?php echo th('fields.tab_climate'); ?>"></q-tab>
                        <q-tab name="water" icon="water_drop" label="<?php echo th('fields.tab_water'); ?>"></q-tab>
                        <q-tab name="weather" icon="thermostat" label="<?php echo th('fields.tab_weather'); ?>"></q-tab>
                    </q-tabs>
                    <q-separator></q-separator>
                    <!-- q-tab-panels for iframe content -->
                    <q-tab-panels v-model="modalTab" class="q-pa-none" style="height: calc(100vh - 110px);">
                        <q-tab-panel name="vegetation" class="q-pa-none">
                            <div v-if="vegetationIframeSrc" style="width: 100%; height: 100%;">
                                <iframe
                                    :src="vegetationIframeSrc"
                                    :key="'vegetation-' + modalTab"
                                    style="width: 100%; height: calc(100% - 10px);  border: none;"
                                    allow="fullscreen"
                                    sandbox="allow-scripts allow-same-origin allow-popups">
                                </iframe>
                            </div>
                            <div v-else class="row items-center justify-center" style="height: 100%;">
                                <div class="text-grey-7"><?php echo th('fields.select_on_map'); ?></div>
                            </div>
                        </q-tab-panel>
                        <q-tab-panel name="climate" class="q-pa-none">
                            <div v-if="climateIframeSrc" style="width: 100%; height: 100%;">
                                <iframe
                                    :src="climateIframeSrc"
                                    :key="'climate-' + modalTab"
                                    style="width: 100%; height: calc(100% - 10px); border: none;"
                                    allow="fullscreen"
                                    sandbox="allow-scripts allow-same-origin allow-popups">
                                </iframe>
                            </div>
                            <div v-else class="row items-center justify-center" style="height: 100%;">
                                <div class="text-grey-7"><?php echo th('fields.select_on_map'); ?></div>
                            </div>
                        </q-tab-panel>
                        <q-tab-panel name="water" class="q-pa-none">
                            <div v-if="waterIframeSrc" style="width: 100%; height: 100%;">
                                <iframe
                                    :src="waterIframeSrc"
                                    :key="'water-' + modalTab"
                                    style="width: 100%; height: calc(100% - 10px); border: none;"
                                    allow="fullscreen"
                                    sandbox="allow-scripts allow-same-origin allow-popups">
                                </iframe>
                            </div>
                            <div v-else class="row items-center justify-center" style="height: 100%;">
                                <div class="text-grey-7"><?php echo th('fields.select_on_map'); ?></div>
                            </div>
                        </q-tab-panel>
                        <q-tab-panel name="weather" class="q-pa-none">
                            <div v-if="weatherIframeSrc" style="width: 100%; height: 100%;">
                                <iframe
                                    :src="weatherIframeSrc"
                                    :key="'weather-' + modalTab"
                                    style="width: 100%; height: calc(100% - 10px); border: none;"
                                    allow="fullscreen"
                                    sandbox="allow-scripts allow-same-origin allow-popups">
                                </iframe>
                            </div>
                            <div v-else class="row items-center justify-center" style="height: 100%;">
                                <div class="text-grey-7"><?php echo th('fields.select_on_map'); ?></div>
                            </div>
                        </q-tab-panel>
                    </q-tab-panels>
                </q-card-section>
            </q-card>
        </q-dialog>

        <!-- Draw Field Modal Dialog -->
        <q-dialog
            v-model="showDrawModal"
            persistent
            max-width="500px">
            <q-card style="min-width: 400px;">
                <q-card-section class="modal-header row items-center">
                    <div class="text-h6"><?php echo th('fields.save_new'); ?></div>
                    <q-space></q-space>
                    <q-btn icon="close" flat round dense v-close-popup></q-btn>
                </q-card-section>
                <q-card-section>
                    <q-btn-toggle
                        v-model="drawTarget"
                        spread
                        no-caps
                        unelevated
                        toggle-color="primary"
                        :options="[
                            { label: '<?php echo th('fields.target_new'); ?>', value: 'new' },
                            { label: '<?php echo th('fields.target_existing'); ?>', value: 'existing' }
                        ]">
                    </q-btn-toggle>
                </q-card-section>
                <q-card-section>
                    <q-input
                        v-if="drawTarget === 'new'"
                        v-model="fieldName"
                        label="<?php echo th('fields.field_name'); ?>"
                        outlined
                        dense
                        autofocus>
                    </q-input>
                    <div v-else>
                        <q-select
                            v-model="outlineFieldId"
                            :options="outlineFieldOptions"
                            label="<?php echo th('fields.target_field'); ?>"
                            emit-value
                            map-options
                            outlined
                            dense>
                        </q-select>
                        <div v-if="outlineReplaces" class="text-caption text-warning q-mt-sm">
                            <?php echo th('fields.outline_replace_warn'); ?>
                        </div>
                    </div>
                </q-card-section>
                <q-card-section>
                    <div class="row q-gutter-sm">
                        <div class="col-auto">
                            <span class="text-caption"><?php echo th('fields.area'); ?></span>
                        </div>
                        <div class="col-auto">
                            <span class="text-caption text-bold">{{ Math.round(drawnArea * 10000) / 10000 }} ha</span>
                        </div>
                    </div>
                </q-card-section>
                <q-card-actions align="right">
                    <q-btn
                        label="<?php echo th('common.cancel'); ?>"
                        color="grey-7"
                        v-close-popup>
                    </q-btn>
                    <q-btn
                        label="<?php echo th('common.save'); ?>"
                        color="primary"
                        :loading="outlineSaving"
                        @click="drawTarget === 'new' ? saveDrawnField() : saveFieldOutline()">
                    </q-btn>
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Not $q.dialog({cancel: true}): its onCancel fires on ESC too, so
             dismissing would open the area form instead of doing nothing. -->
        <q-dialog v-model="showAddChoice">
            <q-card style="min-width: 340px;">
                <q-card-section class="modal-header row items-center">
                    <div class="text-h6"><?php echo th('fields.add'); ?></div>
                    <q-space></q-space>
                    <q-btn icon="close" flat round dense v-close-popup></q-btn>
                </q-card-section>
                <q-card-section><?php echo th('fields.add_question'); ?></q-card-section>
                <q-separator></q-separator>
                <q-card-actions align="right">
                    <q-btn icon="edit_note" label="<?php echo th('fields.add_by_area'); ?>" @click="startNamedField"></q-btn>
                    <q-btn icon="draw" label="<?php echo th('fields.add_by_draw'); ?>" color="primary" @click="startDrawing"></q-btn>
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Add Field by name and area (no outline) -->
        <q-dialog v-model="showNamedModal">
            <q-card style="min-width: 400px;">
                <q-card-section class="modal-header row items-center">
                    <div class="text-h6"><?php echo th('fields.named_title'); ?></div>
                    <q-space></q-space>
                    <q-btn icon="close" flat round dense v-close-popup></q-btn>
                </q-card-section>
                <q-card-section>
                    <q-input
                        v-model="namedForm.name"
                        label="<?php echo th('fields.field_name'); ?>"
                        outlined
                        dense
                        autofocus
                        :disable="namedSaving">
                    </q-input>
                    <q-input
                        v-model="namedForm.area"
                        label="<?php echo th('fields.named_area'); ?>"
                        class="q-mt-md"
                        outlined
                        dense
                        type="text"
                        inputmode="decimal"
                        :disable="namedSaving">
                    </q-input>
                    <div class="text-caption text-grey-7 q-mt-md"><?php echo th('fields.named_hint'); ?></div>
                </q-card-section>
                <q-separator></q-separator>
                <q-card-actions align="right">
                    <q-btn label="<?php echo th('common.cancel'); ?>" v-close-popup></q-btn>
                    <q-btn label="<?php echo th('common.save'); ?>" color="primary" :loading="namedSaving" @click="saveNamedField"></q-btn>
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- New Production (from the crop form's production select) -->
        <q-dialog v-model="showNewProduction">
            <q-card style="width: 500px; max-width: 90vw;">
                <q-card-section class="modal-header row items-center">
                    <div class="text-h6"><?php echo th('plan.new_production_title'); ?></div>
                    <q-space></q-space>
                    <q-btn icon="close" flat round dense v-close-popup></q-btn>
                </q-card-section>
                <q-separator></q-separator>
                <q-card-section>
                    <q-input
                        v-model="newProductionForm.name"
                        label="<?php echo th('plan.new_production_name'); ?>"
                        placeholder="<?php echo th('plan.new_production_name_ph'); ?>"
                        outlined dense autofocus
                        :disable="creatingProduction">
                    </q-input>
                    <q-input
                        v-model="newProductionForm.unit"
                        label="<?php echo th('plan.new_production_unit'); ?>"
                        class="q-mt-md"
                        outlined dense
                        :disable="creatingProduction">
                    </q-input>
                    <q-input
                        v-model="newProductionForm.description"
                        label="<?php echo th('operations.description'); ?>"
                        class="q-mt-md"
                        type="textarea"
                        outlined dense
                        :disable="creatingProduction">
                    </q-input>
                    <div v-if="newProductionError" class="text-negative q-mt-md">{{ newProductionError }}</div>
                </q-card-section>
                <q-separator></q-separator>
                <q-card-actions align="right">
                    <q-btn label="<?php echo th('common.cancel'); ?>" v-close-popup></q-btn>
                    <q-btn label="<?php echo th('common.save'); ?>" color="primary" :loading="creatingProduction" @click="handleAddNewProduction"></q-btn>
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Add / Edit Crop -->
        <q-dialog v-model="showCropForm" persistent maximized>
            <q-card class="form-card" style="width: 100%; max-width: 100vw;">
                <q-card-section class="modal-header row items-center">
                    <div class="text-h6">{{ isEditingCrop ? <?php echo tv('crops.edit_title'); ?> : <?php echo tv('crops.add_title'); ?> }}</div>
                    <q-space></q-space>
                    <q-btn icon="close" flat round dense v-close-popup></q-btn>
                </q-card-section>
                <q-separator></q-separator>
                <q-card-section class="q-pt-md form-scroll">
                    <div class="text-caption text-grey-7 q-mb-md">
                        <q-icon name="pin_drop" size="xs"></q-icon> {{ cropFieldName }}
                    </div>

                    <q-banner v-if="proposal" dense rounded class="q-mb-md"
                        :class="proposal.complete ? 'bg-blue-1 text-blue-10' : 'bg-orange-1 text-orange-10'">
                        <template v-slot:avatar>
                            <q-icon :name="proposal.complete ? 'auto_awesome' : 'warning'"></q-icon>
                        </template>
                        {{ proposal.complete
                            ? <?php echo tj('crops.proposal_ready'); ?>
                            : <?php echo tj('crops.proposal_partial'); ?> }}
                        <ul v-if="proposal.unresolved.length" class="q-my-xs q-pl-md">
                            <li v-for="(u, i) in proposal.unresolved" :key="i">{{ u }}</li>
                        </ul>
                        <template v-slot:action>
                            <q-btn flat dense :label="<?php echo tj('crops.proposal_dismiss'); ?>" @click="proposal = null"></q-btn>
                        </template>
                    </q-banner>

                    <q-form @submit.prevent="saveCrop" class="column q-gutter-md">
                        <q-select
                            ref="productionSelect"
                            v-model="cropForm.production_id"
                            :options="filteredProductionOptions"
                            option-value="id"
                            option-label="name"
                            outlined dense emit-value map-options
                            use-input fill-input hide-selected input-debounce="0"
                            label="<?php echo th('crops.production'); ?>"
                            @filter="filterProductionOptions"
                            :loading="creatingProduction">
                            <template v-slot:after-options>
                                <q-btn icon="add" color="primary" @click="openNewProduction" v-close-popup
                                    class="full-width" label="<?php echo th('plan.new_production'); ?>">
                                </q-btn>
                            </template>
                            <!-- after-options is not rendered when the list is
                                 empty, which is when the button is needed most. -->
                            <template v-slot:no-option>
                                <q-item>
                                    <q-item-section>
                                        <q-btn icon="add" color="primary" @click="openNewProduction" v-close-popup
                                            class="full-width" label="<?php echo th('plan.new_production'); ?>">
                                        </q-btn>
                                    </q-item-section>
                                </q-item>
                            </template>
                        </q-select>

                        <q-input v-model="cropForm.dti" mask="####-##-##" outlined dense label="<?php echo th('crops.start_date'); ?>">
                            <template #append>
                                <q-icon name="event" class="cursor-pointer">
                                    <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                        <q-date v-model="cropForm.dti" mask="YYYY-MM-DD"></q-date>
                                    </q-popup-proxy>
                                </q-icon>
                            </template>
                        </q-input>

                        <div class="row q-col-gutter-md q-ma-none">
                            <div class="col-12 col-sm-6">
                                <q-input v-model="cropForm.dri" type="text" inputmode="decimal" outlined dense label="<?php echo th('crops.dri'); ?>"></q-input>
                            </div>
                            <div class="col-12 col-sm-6">
                                <q-input v-model="cropForm.drd" type="text" inputmode="decimal" outlined dense label="<?php echo th('crops.drd'); ?>"></q-input>
                            </div>
                            <div class="col-12 col-sm-6">
                                <q-input v-model="cropForm.drm" type="text" inputmode="decimal" outlined dense label="<?php echo th('crops.drm'); ?>"></q-input>
                            </div>
                            <div class="col-12 col-sm-6">
                                <q-input v-model="cropForm.drl" type="text" inputmode="decimal" outlined dense label="<?php echo th('crops.drl'); ?>"></q-input>
                            </div>
                        </div>

                        <q-input :model-value="cropEndDate ? cropEndDate + ' · ' + cropCycleDays + ' d' : ''" readonly outlined dense
                            label="<?php echo th('crops.end_date'); ?>"
                            :error="cropCycleDays > 365" error-message="<?php echo th('crops.cycle_too_long'); ?>">
                        </q-input>

                        <q-toggle
                            v-model="showIrrigation"
                            label="<?php echo th('fields.irrigation_params'); ?>"
                            color="secondary">
                        </q-toggle>

                        <div v-if="showIrrigation" class="row q-col-gutter-md q-ma-none">
                            <div class="col-12 col-sm-6">
                                <q-input v-model="cropForm.kci" type="text" inputmode="decimal" outlined dense label="<?php echo th('crops.kci'); ?>"></q-input>
                            </div>
                            <div class="col-12 col-sm-6">
                                <q-input v-model="cropForm.kcm" type="text" inputmode="decimal" outlined dense label="<?php echo th('crops.kcm'); ?>"></q-input>
                            </div>
                            <div class="col-12 col-sm-6">
                                <q-input v-model="cropForm.kce" type="text" inputmode="decimal" outlined dense label="<?php echo th('crops.kce'); ?>"></q-input>
                            </div>
                            <div class="col-12 col-sm-6">
                                <q-input v-model="cropForm.rdi" type="text" inputmode="decimal" outlined dense label="<?php echo th('crops.rdi'); ?>"></q-input>
                            </div>
                            <div class="col-12 col-sm-6">
                                <q-input v-model="cropForm.rdm" type="text" inputmode="decimal" outlined dense label="<?php echo th('crops.rdm'); ?>"></q-input>
                            </div>
                            <div class="col-12 col-sm-6">
                                <q-input v-model="cropForm.iws" type="text" inputmode="decimal" outlined dense label="<?php echo th('crops.iws'); ?>"></q-input>
                            </div>
                            <div class="col-12 col-sm-6">
                                <q-input v-model="cropForm.awc" type="text" inputmode="decimal" outlined dense label="<?php echo th('crops.awc'); ?>"></q-input>
                            </div>
                        </div>
                    </q-form>
                </q-card-section>
                <q-separator></q-separator>
                <q-card-actions align="right">
                    <q-btn v-if="isEditingCrop" icon="delete" label="<?php echo th('common.delete'); ?>" :loading="cropDeleting" @click="confirmDeleteCrop"></q-btn>
                    <q-space></q-space>
                    <q-btn label="<?php echo th('common.cancel'); ?>" v-close-popup></q-btn>
                    <q-btn color="primary" label="<?php echo th('common.save'); ?>" :loading="cropSaving" @click="saveCrop"></q-btn>
                </q-card-actions>
            </q-card>
        </q-dialog>

        <!-- Edit Field Modal Dialog (rename / delete) -->
        <q-dialog
            v-model="showEditModal"
            max-width="500px">
            <q-card style="min-width: 400px;">
                <q-card-section class="modal-header row items-center">
                    <div class="text-h6"><?php echo th('fields.edit_title'); ?></div>
                    <q-space></q-space>
                    <q-btn icon="close" flat round dense v-close-popup></q-btn>
                </q-card-section>
                <q-card-section>
                    <q-input
                        v-model="editFieldName"
                        label="<?php echo th('fields.field_name'); ?>"
                        outlined
                        dense
                        autofocus
                        :disable="editSaving || editDeleting">
                    </q-input>
                </q-card-section>
                <q-separator></q-separator>
                <q-card-actions align="right">
                    <q-btn icon="delete" label="<?php echo th('common.delete'); ?>" :loading="editDeleting" @click="confirmDeleteField"></q-btn>
                    <q-space></q-space>
                    <q-btn label="<?php echo th('common.cancel'); ?>" v-close-popup></q-btn>
                    <q-btn label="<?php echo th('common.save'); ?>" color="primary" :loading="editSaving" @click="saveFieldName"></q-btn>
                </q-card-actions>
            </q-card>
        </q-dialog>
    </div>

    <!-- Vue.js -->
    <script src="https://cdn.jsdelivr.net/npm/vue@3.5.42/dist/vue.global.prod.js"></script>
    <!-- Quasar Framework JS -->
    <script src="https://cdn.jsdelivr.net/npm/quasar@2.20.2/dist/quasar.umd.prod.js"></script>
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

    <!-- Leaflet.draw CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-draw@1.0.4/dist/leaflet.draw.css" />

    <!-- Leaflet.draw JS -->
    <script src="https://cdn.jsdelivr.net/npm/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>

    <!-- Turf.js for area calculation -->
    <script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>

    <script>
        // Initialize Vue + Quasar app
        const app = Vue.createApp({
            data() {
                return {
                    loading: true,
                    tab: 'fields',
                    fieldsData: [],
                    treeData: [],
                    // Fullscreen modal for vegetation/climate analysis
                    showFieldModal: false,
                    modalTab: 'vegetation',
                    selectedFieldName: '',
                    selectedFieldData: null,
                    selectedFieldVertices: [],
                    vegetationUrl: '',
                    climateUrl: '',
                    krdValue: '',
                    selectedNodeKey: null,
                    // Crop form (add / edit a senmfd cycle)
                    showCropForm: false,
                    showIrrigation: false,
                    proposal: null,
                    cropFieldName: '',
                    cropSaving: false,
                    cropDeleting: false,
                    cropForm: {
                        id: '', mmm: '', production_id: null, dti: '',
                        dri: '', kci: '', drd: '', kcm: '', drm: '', kce: '',
                        drl: '', rdi: '', rdm: '', iws: '', awc: ''
                    },
                    productions: [],
                    filteredProductionOptions: [],
                    productionFilterVal: '',
                    creatingProduction: false,
                    showNewProduction: false,
                    newProductionForm: { name: '', unit: 'kg', description: '' },
                    newProductionError: '',
                    // Drawing state
                    isDrawing: false,
                    showDrawModal: false,
                    fieldName: '',
                    drawnArea: 0,
                    drawnLatLngs: [],
                    drawnPolyline: null,
                    drawnPolygon: null,
                    drawControl: null,
                    // Assigning a drawn outline to an existing field
                    drawTarget: 'new',
                    outlineFieldId: null,
                    outlineSaving: false,
                    // Add state (draw an outline, or name + area)
                    showAddChoice: false,
                    showNamedModal: false,
                    namedForm: { name: '', area: null },
                    namedSaving: false,
                    // Edit state (rename / delete of an existing field)
                    showEditModal: false,
                    editFieldId: null,
                    editFieldName: '',
                    // Separate flags: the actions row spins the button that is
                    // actually working, as in crops.php and operations.php.
                    editSaving: false,
                    editDeleting: false
                };
            },

            computed: {
                // Crop keys are 'c'-prefixed and treeData holds only fields, so a
                // selected crop yields undefined and leaves "+ Crop" disabled.
                selectedFieldNode() {
                    return this.treeData.find(n => n.id === this.selectedNodeKey) || null;
                },

                isEditingCrop() {
                    return String(this.cropForm.id ?? '').trim() !== '';
                },

                cropCycleDays() {
                    return ['dri', 'drd', 'drm', 'drl']
                        .reduce((sum, k) => sum + (Math.trunc(Number(this.dot(this.cropForm[k]))) || 0), 0);
                },

                // UTC so a daylight-saving change can't shift the day.
                cropEndDate() {
                    const dti = String(this.cropForm.dti ?? '');
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(dti)) return '';
                    const d = new Date(dti + 'T00:00:00Z');
                    if (Number.isNaN(d.getTime())) return '';
                    d.setUTCDate(d.getUTCDate() + this.cropCycleDays);
                    return d.toISOString().split('T')[0];
                },

                outlineFieldOptions() {
                    return this.fieldsData.map(f => ({
                        label: f.name + ' — ' + (Array.isArray(f.vertices) && f.vertices.length >= 3
                            ? <?php echo tj('fields.outline_replace'); ?>
                            : <?php echo tj('fields.outline_add'); ?>),
                        value: f.id
                    }));
                },

                outlineReplaces() {
                    const field = this.fieldsData.find(f => String(f.id) === String(this.outlineFieldId));
                    return !!field && Array.isArray(field.vertices) && field.vertices.length >= 3;
                },

                // Computed iframe sources for each tab
                vegetationIframeSrc() {
                    if (!this.krdValue || !this.selectedFieldData) return '';
                    return 'vegetation.php' + '?krd=' + encodeURIComponent(this.krdValue) + '&aoi=' + encodeURIComponent(this.selectedFieldData.id);
                },
                climateIframeSrc() {
                    if (!this.krdValue || !this.selectedFieldData) return '';
                    return 'climate.php' + '?krd=' + encodeURIComponent(this.krdValue) + '&aoi=' + encodeURIComponent(this.selectedFieldData.id);
                },
                waterIframeSrc() {
                    if (!this.krdValue || !this.selectedFieldData) return '';
                    return 'water.php' + '?krd=' + encodeURIComponent(this.krdValue) + '&aoi=' + encodeURIComponent(this.selectedFieldData.id);
                },
                weatherIframeSrc() {
                    if (!this.krdValue || !this.selectedFieldData) return '';
                    return 'weather.php' + '?krd=' + encodeURIComponent(this.krdValue) + '&aoi=' + encodeURIComponent(this.selectedFieldData.id);
                }
            },

            methods: {
                // Show notification
                showNotification(message, color, icon) {
                    this.$q.notify({
                        message: message,
                        color: color === 'positive' ? 'positive' : (color === 'warning' ? 'warning' : (color === 'info' ? 'info' : (color === 'primary' ? 'primary' : 'negative'))),
                        icon: icon || 'error',
                        position: 'top',
                        timeout: 4000,
                        html: true
                    });
                },

                // Format area from m² to hectares
                formatArea(area) {
                    return (Math.round(area) / 10000) + ' ha';
                },

                // Crops for every field in one call, grouped by field id. Its own
                // try/catch: the field tree must still render if this fails.
                async loadCrops() {
                    try {
                        const token = localStorage.getItem('auth_token');
                        const headers = { 'Accept': 'application/json' };
                        if (token) headers['Authorization'] = 'Bearer ' + token;

                        const url = '<?php echo ECO_API_URL; ?>field-crops.php?action=read&krd=' +
                            encodeURIComponent(this.krdValue);
                        const response = await fetch(url, { headers: headers });
                        const body = await response.json();
                        if (!response.ok || body.success !== true) return {};

                        const byField = {};
                        (body.data || []).forEach(crop => {
                            (byField[crop.field_id] = byField[crop.field_id] || []).push(crop);
                        });
                        return byField;
                    } catch (error) {
                        console.error('Load crops error:', error);
                        return {};
                    }
                },

                cropRequestHeaders(json) {
                    const token = localStorage.getItem('auth_token');
                    const headers = json ? { 'Content-Type': 'application/json' } : { 'Accept': 'application/json' };
                    if (token) headers['Authorization'] = 'Bearer ' + token;
                    return headers;
                },

                // Lazily: only a user who opens the form needs the catalogue.
                async loadProductions() {
                    if (this.productions.length > 0) return;

                    try {
                        const url = '<?php echo ECO_API_URL; ?>crops.php?action=read&krd=' +
                            encodeURIComponent(this.krdValue);
                        const response = await fetch(url, { headers: this.cropRequestHeaders(false) });
                        const body = await response.json();
                        this.productions = body.data || [];
                        this.filteredProductionOptions = this.productions;
                    } catch (error) {
                        console.error('Load productions error:', error);
                        this.productions = [];
                        this.filteredProductionOptions = [];
                    }
                },

                filterProductionOptions(val, update) {
                    this.productionFilterVal = val;
                    update(() => {
                        if (!val) {
                            this.filteredProductionOptions = this.productions;
                            return;
                        }
                        const needle = val.toLowerCase();
                        this.filteredProductionOptions = this.productions.filter(
                            p => p.name.toLowerCase().includes(needle)
                        );
                    });
                },

                openNewProduction() {
                    this.newProductionForm = {
                        name: String(this.productionFilterVal ?? '').trim(),
                        unit: 'kg',
                        description: ''
                    };
                    this.newProductionError = '';
                    this.showNewProduction = true;
                },

                async handleAddNewProduction() {
                    const name = String(this.newProductionForm.name ?? '').trim();
                    if (this.creatingProduction) return;

                    if (!name) {
                        this.newProductionError = <?php echo tj('plan.new_production_invalid'); ?>;
                        return;
                    }

                    this.creatingProduction = true;
                    this.newProductionError = '';
                    try {
                        const url = '<?php echo ECO_API_URL; ?>crops.php?action=create&krd=' +
                            encodeURIComponent(this.krdValue);
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: this.cropRequestHeaders(true),
                            body: JSON.stringify({
                                name: name,
                                description: this.newProductionForm.description || '',
                                unit: this.newProductionForm.unit || 'kg',
                                krd: this.krdValue
                            })
                        });
                        const body = await response.json();
                        if (!response.ok || !body.success) {
                            this.newProductionError = <?php echo tj('crops.create_failed'); ?> + (body.message || ('HTTP ' + response.status));
                            return;
                        }

                        this.productions.push(body.data);
                        this.filteredProductionOptions = this.productions;
                        this.cropForm.production_id = body.data.id;
                        this.showNewProduction = false;
                        if (this.$refs.productionSelect) this.$refs.productionSelect.hidePopup();
                    } catch (error) {
                        console.error('Create production error:', error);
                        this.newProductionError = <?php echo tj('common.network_error'); ?>;
                    } finally {
                        this.creatingProduction = false;
                    }
                },

                // records.php treats these seven as optional: absent keys go in as
                // NULL on create and are left untouched on update.
                // The inputs are plain text, so a pt-PT keyboard's comma arrives as
                // typed. Everything downstream wants a dot.
                dot(value) {
                    return String(value ?? '').trim().replace(',', '.');
                },

                irrigationKeys() {
                    return ['kci', 'kcm', 'kce', 'rdi', 'rdm', 'iws', 'awc'];
                },

                resetCropForm(fieldId) {
                    this.cropForm = {
                        id: '', mmm: String(fieldId), production_id: null, dti: '',
                        dri: '', kci: '', drd: '', kcm: '', drm: '', kce: '',
                        drl: '', rdi: '', rdm: '', iws: '', awc: ''
                    };
                    this.showIrrigation = false;
                    this.proposal = null;
                },

                async openAddCrop(node) {
                    this.resetCropForm(node.id);
                    this.cropFieldName = node.label;
                    this.showCropForm = true;
                    await this.loadProductions();
                },

                // field-crops.php does not carry the coefficients, so the row is
                // re-read from records.php, which returns every senmfd column.
                async openEditCrop(node) {
                    const crop = node.cropData;
                    this.resetCropForm(crop.field_id);
                    this.cropFieldName = crop.field_name;
                    this.showCropForm = true;
                    await this.loadProductions();

                    try {
                        const url = '<?php echo ECO_API_URL; ?>records.php?krd=' +
                            encodeURIComponent(this.krdValue) + '&aoi=' + encodeURIComponent(crop.field_id);
                        const response = await fetch(url, { headers: this.cropRequestHeaders(false) });
                        const text = await response.text();
                        const rows = JSON.parse(text.trim() || '[]');
                        const row = (Array.isArray(rows) ? rows : []).find(
                            r => String(r.type ?? '').toLowerCase() === 'crop' && String(r.id) === String(crop.crop_id)
                        );
                        if (!row) throw new Error('HTTP ' + response.status);

                        const str = (v, fallback = '') => (v === null || v === undefined ? fallback : String(v));
                        // num, not str: the columns carry trailing zeros and the
                        // inputs are plain text, so 0.7000 would show as typed.
                        const num = (v, fallback = '') =>
                            (v === null || v === undefined || v === '' ? fallback : String(Number(v)));
                        this.cropForm = {
                            id: str(row.id),
                            mmm: String(crop.field_id),
                            production_id: row.production_id ?? null,
                            dti: str(row.dti),
                            dri: num(row.dri), kci: num(row.kci),
                            drd: num(row.drd), kcm: num(row.kcm),
                            drm: num(row.drm), kce: num(row.kce),
                            drl: num(row.drl), rdi: num(row.rdi),
                            rdm: num(row.rdm), iws: num(row.iws), awc: num(row.awc)
                        };

                        this.showIrrigation = this.irrigationKeys().some(
                            k => row[k] !== null && row[k] !== undefined && String(row[k]).trim() !== ''
                        );
                    } catch (error) {
                        console.error('Load crop error:', error);
                        this.showCropForm = false;
                        this.showNotification(<?php echo tj('crops.data_failed'); ?> + error.message, 'negative', 'error');
                    }
                },

                validateCropForm() {
                    const f = this.cropForm;
                    if (f.production_id === null || f.production_id === undefined || f.production_id === '') return false;
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(String(f.dti ?? ''))) return false;

                    const numeric = ['dri', 'drd', 'drm', 'drl']
                        .concat(this.showIrrigation ? this.irrigationKeys() : []);
                    for (const key of numeric) {
                        if (this.dot(f[key]) === '') return false;
                        if (!Number.isFinite(Number(this.dot(f[key])))) return false;
                    }
                    return this.cropCycleDays <= 365;
                },

                async saveCrop() {
                    if (this.cropSaving || this.cropDeleting) return;

                    if (!this.validateCropForm()) {
                        this.showNotification(<?php echo tj('crops.invalid_form'); ?>, 'negative', 'error');
                        return;
                    }

                    this.cropSaving = true;
                    const f = this.cropForm;
                    const payload = { type: 'crop', mmm: String(f.mmm) };
                    ['production_id', 'dti', 'dri', 'drd', 'drm', 'drl']
                        .concat(this.showIrrigation ? this.irrigationKeys() : [])
                        .forEach(key => { payload[key] = this.dot(f[key]); });
                    if (this.isEditingCrop) payload.id = String(f.id).trim();

                    try {
                        await this.postCropRecord(payload);
                        this.showCropForm = false;
                        this.showNotification(<?php echo tj('crops.saved'); ?>, 'positive', 'check');
                        await this.loadFields(this.krdValue);
                    } catch (error) {
                        console.error('Save crop error:', error);
                        this.showNotification(<?php echo tj('crops.save_failed'); ?> + error.message, 'negative', 'error');
                    } finally {
                        this.cropSaving = false;
                    }
                },

                async postCropRecord(payload) {
                    const url = '<?php echo ECO_API_URL; ?>records.php?krd=' +
                        encodeURIComponent(this.krdValue) + '&aoi=' + encodeURIComponent(payload.mmm);
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: this.cropRequestHeaders(true),
                        body: JSON.stringify(payload)
                    });
                    if (!response.ok) {
                        const body = await response.json().catch(() => ({}));
                        throw new Error(body.message || ('HTTP ' + response.status));
                    }
                },

                confirmDeleteCrop() {
                    if (!this.isEditingCrop || this.cropSaving || this.cropDeleting) return;

                    this.$q.dialog({
                        title: <?php echo tj('common.confirm_delete'); ?>,
                        message: <?php echo tj('crops.delete_confirm'); ?>,
                        cancel: true,
                        persistent: true
                    }).onOk(() => {
                        this.deleteCrop();
                    });
                },

                async deleteCrop() {
                    this.cropDeleting = true;
                    const id = String(this.cropForm.id).trim();

                    try {
                        // delete carries the senmfd.xxx row id, not a flag.
                        await this.postCropRecord({
                            type: 'crop',
                            mmm: String(this.cropForm.mmm),
                            id: id,
                            action: 'delete',
                            delete: id
                        });
                        this.showCropForm = false;
                        this.showNotification(<?php echo tj('crops.deleted'); ?>, 'positive', 'check');
                        await this.loadFields(this.krdValue);
                    } catch (error) {
                        console.error('Delete crop error:', error);
                        this.showNotification(<?php echo tj('crops.delete_failed'); ?> + error.message, 'negative', 'error');
                    } finally {
                        this.cropDeleting = false;
                    }
                },

                // Convert API data to tree nodes
                convertToTreeNodes(fields, cropsByField) {
                    return fields.map((field, index) => {
                        // Ochre marks a field with no outline: the same test
                        // loadFields() uses to decide what goes on the map.
                        const color = Array.isArray(field.vertices) && field.vertices.length >= 3
                            ? 'var(--q-primary)'
                            : '#CC7722';
                        return {
                            id: field.id,
                            label: field.name,
                            areaCaption: this.formatArea(field.area),
                            icon: 'pin_drop',
                            color: color,
                            fieldData: field,
                            // 'c' prefix: node-key is shared across the whole tree
                            // and a crop id can equal a field id.
                            children: ((cropsByField || {})[field.id] || []).map(crop => ({
                                id: 'c' + crop.crop_id,
                                label: crop.production_name,
                                areaCaption: crop.dti + ' → ' + crop.end_date + ' · ' + crop.cycle_days + ' d',
                                icon: 'grass',
                                color: color,
                                cropData: crop
                            }))
                        };
                    });
                },

                // No crops means no expand arrow, so the row starts 10px
                // (the arrow box, less Quasar's own leaf indent) to the left.
                rowIndent(node) {
                    return node.children && node.children.length
                        ? null
                        : 'padding-left: 8px';
                },

                // Takes the node KEY, not the node.
                handleFieldSelect(key) {
                    const node = this.treeData.find(n => n.id === key);
                    if (!node || !node.fieldData) return;

                    // Fit map to the selected field polygon
                    if (node.fieldData.vertices && node.fieldData.vertices.length > 0) {
                        const latLngs = node.fieldData.vertices.map(v => [v[0], v[1]]);
                        this.currentMap.fitBounds(latLngs, {
                            padding: [50, 50]
                        });
                    }
                    this.showNotification(<?php echo tj('fields.selected'); ?> + node.label, 'primary', 'pin_drop');
                },

                // Load and display GeoJSON fields
                async loadFields(krd) {
                    this.krdValue = krd;
                    this.loading = true;
                    const self = this; // Capture at top of function for use in callbacks

                    try {
                        // Get token for authorization
                        const token = localStorage.getItem('auth_token');

                        const headers = {
                            'Accept': 'application/json'
                        };

                        if (token) {
                            headers['Authorization'] = 'Bearer ' + token;
                        }

                        const url = '<?php echo ECO_API_URL; ?>fields.php?krd=' + encodeURIComponent(krd) + '&map=aoi';
                        const response = await fetch(url, {
                            headers: headers
                        });

                        if (!response.ok) {
                            throw new Error(<?php echo tj('fields.load_error'); ?> + response.status);
                        }

                        const data = await response.json();
                        this.fieldsData = data;

                        // Create tree nodes
                        this.treeData = this.convertToTreeNodes(data, await this.loadCrops());

                        // Only initialize map once
                        if (!this.currentMap) {
                            this.currentMap = L.map('map').setView([38.710, -9.185], 15);

                            // Leaflet focuses the container on mousedown and undoes the
                            // scroll that causes with window.scrollTo -- which only works
                            // when the document is the scroller. Ours is #q-app, so make
                            // the container's focus() not scroll in the first place.
                            // (preventScroll: Chrome 64+, Firefox 68+, Safari 15+.)
                            const mapEl = this.currentMap.getContainer();
                            const nativeFocus = mapEl.focus.bind(mapEl);
                            mapEl.focus = opts => nativeFocus({ ...opts, preventScroll: true });

                            // Add tile layer (only once)
                            // OSM up to zoom 15, Esri World Imagery from 16.
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                maxZoom: 15,
                                attribution: '\u00a9 OpenStreetMap contributors'
                            }).addTo(this.currentMap);
                            L.tileLayer('https://ibasemaps-api.arcgis.com/arcgis/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}?token=' + <?php echo json_encode(ARCGIS_TOKEN); ?>, {
                                minZoom: 16,
                                maxZoom: 19,
                                attribution: 'Powered by Esri | Source: Esri, Maxar, Earthstar Geographics'
                            }).addTo(this.currentMap);

                            // Add Leaflet.draw control (always visible)
                            this.drawControl = new L.Control.Draw({
                                draw: {
                                    polyline: false,
                                    circle: false,
                                    marker: false,
                                    circlemarker: false,
                                    rectangle: false,
                                    polygon: {
                                        allowIntersection: false,
                                        showArea: true,
                                        shapeOptions: {
                                            color: '#1976D2',
                                            fillColor: '#1976D2',
                                            fillOpacity: 0.2,
                                            weight: 2
                                        }
                                    }
                                }
                            });
                            this.currentMap.addControl(this.drawControl);

                            // Listen for drawn polygon
                            this.currentMap.on(L.Draw.Event.CREATED, function(e) {
                                self.onDrawingComplete(e);
                            });
                        }

                        // Remove leaflet-draw drawn layers (polylines, circles, markers, etc.) before refreshing
                        if (this.currentMap) {
                            this.currentMap.eachLayer(function(layer) {
                                if (layer instanceof L.Polygon || layer instanceof L.Polyline ||
                                    layer instanceof L.Circle || layer instanceof L.CircleMarker ||
                                    layer instanceof L.Marker || layer instanceof L.ImageOverlay) {
                                    this.currentMap.removeLayer(layer);
                                }
                            }, this);
                        }

                        // Remove old GeoJSON layer if exists
                        if (this.currentGeoJSONLayer) {
                            this.currentMap.removeLayer(this.currentGeoJSONLayer);
                        }

                        // A field registered by name and area has no outline.
                        // It belongs in the tree, not on the map - and closing
                        // the ring below on an empty list throws and takes the
                        // whole layer with it.
                        const mappable = (data || []).filter(
                            item => Array.isArray(item.vertices) && item.vertices.length >= 3
                        );

                        if (mappable.length > 0) {
                            // Convert vertices to GeoJSON FeatureCollection
                            const geojsonFeatureCollection = {
                                type: 'FeatureCollection',
                                features: mappable.map(item => ({
                                    type: 'Feature',
                                    properties: {
                                        id: item.id,
                                        name: item.name,
                                        area: item.area
                                    },
                                    geometry: {
                                        type: 'Polygon',
                                        coordinates: [
                                            [
                                                ...item.vertices.map(v => [v[1], v[0]]), // Convert [lat, lng] to [lng, lat]
                                                [item.vertices[0][1], item.vertices[0][0]] // Close the polygon
                                            ]
                                        ]
                                    }
                                }))
                            };

                            // Add GeoJSON layer to map
                            this.currentGeoJSONLayer = L.geoJSON(geojsonFeatureCollection, {
                                style: function(feature) {
                                    return {
                                        color: '#2196F3',
                                        weight: 2,
                                        opacity: 0.8,
                                        fillColor: '#2196F3',
                                        fillOpacity: 0.3
                                    };
                                },
                                onEachFeature: function(feature, layer) {
                                    // Attach click handler directly to each polygon layer
                                    layer.on({
                                        click: function(e) {
                                            L.DomEvent.stopPropagation(e);
                                            L.DomEvent.preventDefault(e);
                                            self.openVegetationModal(feature);
                                        }
                                    });
                                }
                            }).addTo(this.currentMap);

                            // Only fit bounds on initial load, not on refresh
                            if (!this._hasLoadedFields) {
                                this.currentMap.fitBounds(this.currentGeoJSONLayer.getBounds(), {
                                    padding: [50, 50]
                                });
                                this._hasLoadedFields = true;
                            }
                        } else {
                            this.showNotification(<?php echo tj('fields.none_for_krd'); ?>, 'warning', 'warning');
                        }

                    } catch (error) {
                        console.error('Load fields error:', error);
                        this.showNotification(<?php echo tj('fields.load_failed'); ?>, 'negative', 'cloud_off');
                    } finally {
                        this.loading = false;
                    }
                },


                // Open vegetation analysis modal for a field (auto-load iframe)
                openVegetationModal(feature) {
                    if (!feature || !feature.properties) return;

                    this.selectedFieldName = feature.properties.name || <?php echo tj('fields.unnamed'); ?>;
                    this.selectedFieldData = feature.properties;
                    this.krdValue = this.krdValue || '';
                    this.modalTab = 'vegetation'; // Reset to vegetation tab

                    // Expose field data for the iframe to access
                    window.VegetationModal = {
                        getFieldVertices: () => {
                            if (feature.geometry && feature.geometry.coordinates && feature.geometry.coordinates[0]) {
                                return feature.geometry.coordinates[0].slice(0, -1).map(coord => [coord[1], coord[0]]);
                            }
                            return [];
                        }
                    };

                    this.showFieldModal = true;
                },

                // Routing hop for an assistant crop proposal. The shell delivers
                // through the 'fields' target, then waits for a 'crops' target
                // whose meta.aoi matches; registering it here is what releases
                // the queued payload into applyProposal().
                async openFieldById(fieldId, tab) {
                    const node = this.treeData.find(n => String(n.id) === String(fieldId));
                    if (!node) {
                        this.showNotification(<?php echo tj('fields.proposal_field_missing'); ?>, 'warning', 'warning');
                        return false;
                    }

                    await this.openAddCrop(node);

                    if (window.top !== window && typeof window.top.mskRegisterProposalTarget === 'function') {
                        window.top.mskRegisterProposalTarget(
                            'crops',
                            (payload) => this.applyProposal(payload),
                            { aoi: fieldId }
                        );
                    }
                    return true;
                },

                // Overwrites only what the assistant resolved, on top of the blank
                // form openAddCrop() just built. Nothing is submitted: the user
                // reviews and presses Save, running the same saveCrop() as always.
                applyProposal(payload) {
                    if (!payload || typeof payload !== 'object') return;

                    if (payload.production_id) {
                        // The catalogue hands ids back as strings, so a strict
                        // lookup misses a numeric 8 against an option valued "8".
                        const hit = this.productions.find(p => Number(p.id) === Number(payload.production_id));
                        if (hit) this.cropForm.production_id = hit.id;
                    }
                    if (payload.dti) this.cropForm.dti = String(payload.dti);

                    let applied = 0;
                    ['dri', 'drd', 'drm', 'drl'].concat(this.irrigationKeys()).forEach(key => {
                        const value = this.toAiNumberOrNull(payload[key]);
                        if (value === null) return;
                        this.cropForm[key] = String(value);
                        applied++;
                    });

                    // Open the panel if the assistant filled any of it, or those
                    // values would be sent without ever being shown.
                    if (this.irrigationKeys().some(k => this.toAiNumberOrNull(payload[k]) !== null)) {
                        this.showIrrigation = true;
                    }

                    this.proposal = {
                        unresolved: Array.isArray(payload.unresolved) ? payload.unresolved : [],
                        complete: payload.complete === true
                    };
                },

                toAiNumberOrNull(value) {
                    if (value === null || value === undefined) return null;
                    if (typeof value === 'number') return Number.isFinite(value) ? value : null;

                    const text = String(value).trim().replace(',', '.');
                    if (text === '') return null;

                    const direct = Number(text);
                    if (Number.isFinite(direct)) return direct;

                    const match = text.match(/-?\d+(?:\.\d+)?/);
                    return match && Number.isFinite(Number(match[0])) ? Number(match[0]) : null;
                },

                // Close vegetation modal and cleanup
                closeVegetationModal() {
                    this.showFieldModal = false;
                    // Cleanup exposed API after transition
                    setTimeout(() => {
                        window.VegetationModal = null;
                        this.selectedFieldName = '';
                        this.selectedFieldData = null;
                    }, 300);
                },

                // Handle before dialog leaves to cleanup resources
                onModalBeforeLeave() {
                    window.VegetationModal = null;
                    this.selectedFieldName = '';
                    this.selectedFieldData = null;
                },

                // Handle drawing completion
                onDrawingComplete(e) {
                    const layer = e.layer;
                    this.drawnPolygon = layer;

                    // Get the LatLngs from the polygon
                    let latLngs;
                    if (layer.getLatLngs) {
                        latLngs = layer.getLatLngs();
                        if (latLngs[0] && latLngs[0].length) {
                            // Polygon ring
                            latLngs = latLngs[0];
                        }
                    }

                    if (!latLngs || latLngs.length < 3) {
                        this.showNotification(<?php echo tj('fields.polygon_min'); ?>, 'warning', 'warning');
                        return;
                    }

                    // Convert to [lat, lng] array and clear existing layers
                    if (this.drawnPolyline) {
                        this.currentMap.removeLayer(this.drawnPolyline);
                    }
                    this.drawnPolyline = L.polyline(latLngs, {
                        color: '#FF5722',
                        weight: 3,
                        dashArray: '10, 10'
                    }).addTo(this.currentMap);

                    this.drawnLatLngs = [];
                    for (let i = 0; i < latLngs.length; i++) {
                        this.drawnLatLngs.push([latLngs[i].lat, latLngs[i].lng]);
                    }

                    // Calculate area using Turf.js
                    const coordsForTurf = [];
                    for (let i = 0; i < latLngs.length; i++) {
                        coordsForTurf.push([latLngs[i].lng, latLngs[i].lat]);
                    }
                    coordsForTurf.push(coordsForTurf[0]); // Close the polygon for Turf

                    // Hectares, as sendfd.arf stores it. turf.area() is m².
                    const polygonFeature = turf.polygon([coordsForTurf]);
                    this.drawnArea = Math.abs(turf.area(polygonFeature)) / 10000;

                    // Remove only the drawn polygon layer, keep draw control for multiple drawings
                    this.currentMap.removeLayer(this.drawnPolygon);
                    this.drawnPolygon = null;

                    this.isDrawing = false;
                    this.fieldName = '';
                    this.drawTarget = 'new';
                    this.outlineFieldId = null;
                    this.showDrawModal = true;
                },

                // Save the drawn field
                async saveDrawnField() {
                    if (!this.fieldName || this.fieldName.trim() === '') {
                        this.showNotification(<?php echo tj('fields.name_required'); ?>, 'warning', 'warning');
                        return;
                    }

                    if (!this.krdValue) {
                        this.showNotification(<?php echo tj('fields.krd_unavailable'); ?>, 'negative', 'error');
                        return;
                    }

                    if (this.drawnLatLngs.length < 3) {
                        this.showNotification(<?php echo tj('fields.polygon_min'); ?>, 'warning', 'warning');
                        return;
                    }

                    const payload = {
                        krd: this.krdValue,
                        name: this.fieldName.trim(),
                        vertices: this.drawnLatLngs,
                        // Informational: the server recomputes arf with ST_Area.
                        area: this.drawnArea,
                        timestamp: Date.now()
                    };

                    // Get token for authorization
                    const token = localStorage.getItem('auth_token');

                    const headers = {
                        'Content-Type': 'application/json'
                    };

                    if (token) {
                        headers['Authorization'] = 'Bearer ' + token;
                    }

                    try {
                        const response = await fetch('<?php echo ECO_API_URL; ?>fields-save.php', {
                            method: 'POST',
                            headers: headers,
                            body: JSON.stringify(payload)
                        });

                        if (!response.ok) {
                            throw new Error(<?php echo tj('fields.save_error'); ?> + response.status);
                        }

                        // API returns empty body with 200, so just check success and reload
                        this.showNotification(<?php echo tj('fields.saved_1'); ?> + this.fieldName.trim() + <?php echo tj('fields.saved_2'); ?>, 'positive', 'check');

                        // Close modal and cleanup
                        this.showDrawModal = false;
                        this.fieldName = '';
                        this.drawnLatLngs = [];
                        this.drawnArea = 0;

                        // Refresh polygons by re-fetching from server
                        if (this.krdValue) {
                            this.loadFields(this.krdValue);
                        }
                    } catch (error) {
                        console.error('Save field error:', error);
                        this.showNotification(<?php echo tj('fields.save_failed'); ?> + error.message, 'negative', 'error');
                    }
                },

                async saveFieldOutline() {
                    if (!this.outlineFieldId) {
                        this.showNotification(<?php echo tj('fields.field_required'); ?>, 'warning', 'warning');
                        return;
                    }

                    if (this.drawnLatLngs.length < 3) {
                        this.showNotification(<?php echo tj('fields.polygon_min'); ?>, 'warning', 'warning');
                        return;
                    }

                    this.outlineSaving = true;
                    try {
                        await this.postFieldEdit('set-outline', {
                            id: this.outlineFieldId,
                            vertices: this.drawnLatLngs
                        });
                        this.showDrawModal = false;
                        this.drawnLatLngs = [];
                        this.drawnArea = 0;
                        this.showNotification(<?php echo tj('fields.outline_saved'); ?>, 'positive', 'check');
                        await this.loadFields(this.krdValue);
                    } catch (error) {
                        console.error('Set outline error:', error);
                        if (error.code === 'quota') {
                            this.showNotification(error.message, 'warning', 'warning');
                        } else {
                            this.showNotification(<?php echo tj('fields.outline_failed'); ?> + error.message, 'negative', 'error');
                        }
                    } finally {
                        this.outlineSaving = false;
                    }
                },

                // The toolbar button, not a handler of our own: its Cancel/Finish
                // bar is what lets the user back out.
                startDrawing() {
                    this.showAddChoice = false;
                    this.currentMap?.getContainer()
                        .querySelector('.leaflet-draw-draw-polygon')?.click();
                },

                startNamedField() {
                    this.showAddChoice = false;
                    this.namedForm = { name: '', area: null };
                    this.showNamedModal = true;
                },

                // Area is hectares: create-named writes it straight into sendfd.arf.
                async saveNamedField() {
                    const name = (this.namedForm.name || '').trim();
                    const area = Number(this.dot(this.namedForm.area));

                    if (name === '' || !Number.isFinite(area) || area <= 0) {
                        this.showNotification(<?php echo tj('fields.named_invalid'); ?>, 'warning', 'warning');
                        return;
                    }

                    this.namedSaving = true;
                    try {
                        await this.postFieldEdit('create-named', { name: name, area: area });
                        this.showNamedModal = false;
                        this.showNotification(<?php echo tj('fields.created'); ?>, 'positive', 'check');
                        await this.loadFields(this.krdValue);
                    } catch (error) {
                        console.error('Create field error:', error);
                        if (error.code === 'quota') {
                            this.showNotification(error.message, 'warning', 'warning');
                        } else {
                            this.showNotification(<?php echo tj('fields.create_failed'); ?> + error.message, 'negative', 'error');
                        }
                    } finally {
                        this.namedSaving = false;
                    }
                },

                // Open the rename/delete dialog for one tree node
                openEditField(node) {
                    if (!node) return;
                    this.editFieldId = node.id;
                    this.editFieldName = node.label;
                    this.showEditModal = true;
                },

                // Both edit actions go to fields-save.php?action=..., which -- unlike
                // the create path -- answers with a {success, message} envelope. The
                // message is what carries the "field has crops" refusal, so it is
                // read on failure as well as on a non-2xx status.
                async postFieldEdit(action, payload) {
                    const token = localStorage.getItem('auth_token');

                    const headers = {
                        'Content-Type': 'application/json'
                    };

                    if (token) {
                        headers['Authorization'] = 'Bearer ' + token;
                    }

                    const response = await fetch('<?php echo ECO_API_URL; ?>fields-save.php?action=' + action, {
                        method: 'POST',
                        headers: headers,
                        body: JSON.stringify({ krd: this.krdValue, ...payload })
                    });

                    const body = await response.json().catch(() => ({}));

                    if (!response.ok || body.success !== true) {
                        // The server's message is English. Translate the one refusal a
                        // user meets in normal use; the rest are exceptional paths.
                        const error = new Error(
                            body.code === 'has_records'
                                ? <?php echo tj('fields.delete_blocked'); ?>
                                : (body.code === 'quota'
                                    ? <?php echo tj('fields.quota_blocked'); ?>
                                    : (body.message || ('HTTP ' + response.status)))
                        );
                        error.code = body.code || '';
                        throw error;
                    }
                },

                // Rename the field (sendfd.dsc only)
                async saveFieldName() {
                    const name = (this.editFieldName || '').trim();

                    if (name === '') {
                        this.showNotification(<?php echo tj('fields.name_required'); ?>, 'warning', 'warning');
                        return;
                    }

                    this.editSaving = true;
                    try {
                        await this.postFieldEdit('rename', { id: this.editFieldId, name: name });
                        this.showEditModal = false;
                        this.showNotification(<?php echo tj('fields.renamed'); ?>, 'positive', 'check');
                        await this.loadFields(this.krdValue);
                    } catch (error) {
                        console.error('Rename field error:', error);
                        this.showNotification(<?php echo tj('fields.rename_failed'); ?> + error.message, 'negative', 'error');
                    } finally {
                        this.editSaving = false;
                    }
                },

                confirmDeleteField() {
                    if (this.editSaving || this.editDeleting) return;

                    this.$q.dialog({
                        title: <?php echo tj('common.confirm_delete'); ?>,
                        message: <?php echo tj('fields.delete_confirm'); ?>.replace('{name}', this.editFieldName),
                        cancel: true,
                        persistent: true
                    }).onOk(() => {
                        this.deleteField();
                    });
                },

                async deleteField() {
                    this.editDeleting = true;
                    try {
                        await this.postFieldEdit('delete', { id: this.editFieldId });
                        this.showEditModal = false;
                        this.showNotification(<?php echo tj('fields.deleted'); ?>, 'positive', 'check');
                        await this.loadFields(this.krdValue);
                    } catch (error) {
                        console.error('Delete field error:', error);
                        // A blocked delete is a normal answer, not a failure: show it
                        // on its own rather than behind the "could not delete" prefix.
                        if (error.code === 'has_records') {
                            this.showNotification(error.message, 'warning', 'warning');
                        } else {
                            this.showNotification(<?php echo tj('fields.delete_failed'); ?> + error.message, 'negative', 'error');
                        }
                    } finally {
                        this.editDeleting = false;
                    }
                }
            },

            mounted() {
                this.currentMap = null;
                this.currentGeoJSONLayer = null;

                // Read krd from URL param first, fallback to parent window
                const urlParams = new URLSearchParams(window.location.search);
                const krd = urlParams.get('krd') || window.parent.krd;

                if (krd) {
                    // Registered only once the fields are on the map: the router
                    // resolves an id against the Leaflet layer, so registering
                    // earlier would answer "no such field" for every proposal
                    // that arrived during the load. The shell queues until then.
                    const registerRouter = () => {
                        if (window.top === window || typeof window.top.mskRegisterProposalTarget !== 'function') return;

                        window.top.mskRegisterProposalTarget('fields', (request) => this.openFieldById(request.field_id, request.tab));

                        window.addEventListener('beforeunload', () => {
                            if (typeof window.top.mskUnregisterProposalTarget === 'function') {
                                window.top.mskUnregisterProposalTarget('fields');
                                window.top.mskUnregisterProposalTarget('crops');
                            }
                        });
                    };

                    Promise.resolve(this.loadFields(krd)).catch(() => {}).finally(registerRouter);
                } else {
                    this.showNotification(<?php echo tj('common.krd_missing'); ?>, 'warning', 'warning');
                    this.loading = false;
                }
            }
        });

        app.use(Quasar);
        app.mount('#q-app');
    </script>
</body>

</html>