<div class="sticky-top section1 navbarx" style="margin: 0; padding-top: 0px;">
    <nav class="navbar navbar-light navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><img src="msk/logoi.png" alt="AGRINEXO .PT"></a>
            <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarT">
                <span class="visually-hidden"><?php echo th('shell.toggle_nav'); ?></span>
                <span class="navbar-toggler-icon"></span>
            </button>
            <div id="navbarT" class="collapse navbar-collapse">
                <div class="row ms-auto">
                    <div class="col-12 ps-0">
                        <ul class="navbar-nav float-none float-lg-end d-flex justify-content-start navbar-nav-scroll">
                            <li class="nav-item ms-lg-auto move-right">
                            <li class="nav-item" id="installItem" hidden><a class="nav-link text-nowrap" href="#"
                                    onclick="mskInstall(); return false;" title="<?php echo th('shell.install'); ?>"><i
                                        class="bi bi-download"></i></a></li>
                         </li>
                        </ul>
                    </div>
                    <div class="col-12 ps-0 order-lg-first">
                        <ul class="navbar-nav d-flex justify-content-end navbar-nav-scroll">
                            <li class="nav-item">
                                <div style="width: 95px;height: 28px;display: inline-block;">
                                    <i class="bi bi-sun-fill" style="font-size: 17px; padding-right: 3px"></i>&nbsp;
                                    <div class="form-check form-switch"
                                        style="vertical-align: middle; display: inline-block;">
                                        <input class="form-check-input" type="checkbox" id="darkModeSwitch">
                                    </div>
                                    <i class="bi bi-moon-fill" style="padding-left: 0px;"></i>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</div>